<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

require_csrf();
$user = require_login(true);
$uid = (int)$user['id'];
$action = $_POST['action'] ?? '';
$pdo = db();

switch ($action) {
    case 'create': {
        if ($user['role'] !== 'buyer') {
            json_out(['ok' => false, 'error' => 'Only Buyer accounts can place orders'], 403);
        }
        $gigId = (int)($_POST['gig_id'] ?? 0);
        $requirements = trim((string)($_POST['requirements'] ?? ''));

        $stmt = $pdo->prepare("SELECT g.*, u.status AS seller_status FROM gigs g JOIN users u ON u.id = g.seller_id WHERE g.id = ? AND g.status = 'active'");
        $stmt->execute([$gigId]);
        $gig = $stmt->fetch();
        if (!$gig || $gig['seller_status'] !== 'active') {
            json_out(['ok' => false, 'error' => 'Gig is unavailable'], 404);
        }
        if ((int)$gig['seller_id'] === $uid) {
            json_out(['ok' => false, 'error' => 'You cannot order your own gig'], 422);
        }

        $price = (float)$gig['price'];
        $commissionPct = max(0, min(50, (float)get_setting('commission_pct', '10')));
        $platformFee = round($price * ($commissionPct / 100), 2);
        $sellerNet = round($price - $platformFee, 2);

        $pdo->beginTransaction();
        try {
            $balStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
            $balStmt->execute([$uid]);
            $balance = (float)$balStmt->fetchColumn();

            if ($balance < $price) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Insufficient wallet balance (' . money($balance) . '). Add funds in Buyer Panel.'], 422);
            }

            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?')->execute([$price, $uid]);

            $code = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $dueAt = date('Y-m-d H:i:s', time() + ((int)$gig['delivery_days'] * 86400));

            $ins = $pdo->prepare('INSERT INTO orders (order_code, gig_id, buyer_id, seller_id, gig_title, price, platform_fee, seller_net, total_paid, delivery_days, revisions_left, requirements, status, due_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                $code,
                $gigId,
                $uid,
                (int)$gig['seller_id'],
                $gig['title'],
                $price,
                $platformFee,
                $sellerNet,
                $price,
                (int)$gig['delivery_days'],
                (int)$gig['revisions'],
                $requirements !== '' ? $requirements : 'Standard gig delivery.',
                'pending',
                $dueAt,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, ?, ?)')
                ->execute([$orderId, $uid, 'created', 'Order placed (' . money($price) . ')']);
            $pdo->prepare('UPDATE gigs SET orders_count = orders_count + 1 WHERE id = ?')->execute([$gigId]);

            find_or_create_conversation($uid, (int)$gig['seller_id'], $gigId, $orderId);

            $pdo->commit();
            json_out(['ok' => true, 'order_id' => $orderId, 'order_code' => $code, 'redirect' => 'order.php?id=' . $orderId]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Order creation failed'], 500);
        }
    }

    case 'start': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || (int)$order['seller_id'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Order not found'], 404);
        }
        if ($order['status'] !== 'pending') {
            json_out(['ok' => false, 'error' => 'Order cannot be started'], 422);
        }
        $pdo->prepare("UPDATE orders SET status = 'in_progress' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'started', 'Seller accepted and started working.')")
            ->execute([$orderId, $uid]);
        json_out(['ok' => true, 'status' => 'in_progress']);
    }

    case 'deliver': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $note = trim((string)($_POST['delivery_note'] ?? ''));
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || (int)$order['seller_id'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Order not found'], 404);
        }
        if (!in_array($order['status'], ['pending', 'in_progress', 'revision'], true)) {
            json_out(['ok' => false, 'error' => 'Cannot deliver in current state'], 422);
        }
        if (mb_strlen($note) < 5) {
            json_out(['ok' => false, 'error' => 'Provide a delivery note'], 422);
        }

        $filePath = $order['delivery_file'];
        if (!empty($_FILES['delivery_file']) && ($_FILES['delivery_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload($_FILES['delivery_file'], 'deliverables', 'file');
            if (!$up['ok']) {
                json_out(['ok' => false, 'error' => $up['error']], 422);
            }
            $filePath = $up['path'];
        }

        $pdo->prepare("UPDATE orders SET status = 'delivered', delivery_note = ?, delivery_file = ?, delivered_at = NOW() WHERE id = ?")
            ->execute([$note, $filePath, $orderId]);
        $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note, attachment) VALUES (?, ?, 'delivered', ?, ?)")
            ->execute([$orderId, $uid, $note, $filePath]);
        json_out(['ok' => true, 'status' => 'delivered']);
    }

    case 'request_revision': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $note = trim((string)($_POST['revision_note'] ?? ''));
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || (int)$order['buyer_id'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Order not found'], 404);
        }
        if ($order['status'] !== 'delivered') {
            json_out(['ok' => false, 'error' => 'Revisions can only be requested after delivery'], 422);
        }
        if ((int)$order['revisions_left'] <= 0) {
            json_out(['ok' => false, 'error' => 'No revisions remaining'], 422);
        }
        if (mb_strlen($note) < 5) {
            json_out(['ok' => false, 'error' => 'Describe what needs adjusting'], 422);
        }

        $pdo->prepare("UPDATE orders SET status = 'revision', revision_note = ?, revisions_left = revisions_left - 1 WHERE id = ?")
            ->execute([$note, $orderId]);
        $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'revision', ?)")
            ->execute([$orderId, $uid, $note]);
        json_out(['ok' => true, 'status' => 'revision']);
    }

    case 'complete': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order || (int)$order['buyer_id'] !== $uid) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Order not found'], 404);
            }
            if ($order['status'] !== 'delivered') {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Order must be delivered before completion'], 422);
            }

            $pdo->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ?, earnings_total = earnings_total + ? WHERE id = ?')
                ->execute([(float)$order['seller_net'], (float)$order['seller_net'], (int)$order['seller_id']]);
            $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'completed', 'Buyer approved delivery and released funds.')")
                ->execute([$orderId, $uid]);
            $pdo->commit();
            json_out(['ok' => true, 'status' => 'completed']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Could not complete order'], 500);
        }
    }

    case 'cancel': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Order not found'], 404);
            }
            $isBuyer = (int)$order['buyer_id'] === $uid;
            $isSeller = (int)$order['seller_id'] === $uid;
            if (!$isBuyer && !$isSeller) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Permission denied'], 403);
            }
            $cancellable = $isSeller ? ['pending', 'in_progress'] : ['pending'];
            if (!in_array($order['status'], $cancellable, true)) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Order cannot be cancelled at this stage'], 422);
            }

            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')
                ->execute([(float)$order['total_paid'], (int)$order['buyer_id']]);
            $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'cancelled', 'Order cancelled and refunded to buyer wallet.')")
                ->execute([$orderId, $uid]);
            $pdo->commit();
            json_out(['ok' => true, 'status' => 'cancelled']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Cancellation failed'], 500);
        }
    }

    case 'dispute': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'Dispute opened for admin resolution.'));
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || ((int)$order['buyer_id'] !== $uid && (int)$order['seller_id'] !== $uid)) {
            json_out(['ok' => false, 'error' => 'Order not found'], 404);
        }
        if (!in_array($order['status'], ['in_progress', 'delivered', 'revision'], true)) {
            json_out(['ok' => false, 'error' => 'Cannot dispute this order'], 422);
        }
        $pdo->prepare("UPDATE orders SET status = 'disputed' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'disputed', ?)")
            ->execute([$orderId, $uid, $reason]);
        json_out(['ok' => true, 'status' => 'disputed']);
    }

    case 'review': {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim((string)($_POST['comment'] ?? ''));

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || (int)$order['buyer_id'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Order not found'], 404);
        }
        if ($order['status'] !== 'completed') {
            json_out(['ok' => false, 'error' => 'Complete the order before reviewing'], 422);
        }
        if (mb_strlen($comment) < 4) {
            json_out(['ok' => false, 'error' => 'Write a short review comment'], 422);
        }

        $exists = $pdo->prepare('SELECT id FROM reviews WHERE order_id = ?');
        $exists->execute([$orderId]);
        if ($exists->fetchColumn()) {
            json_out(['ok' => false, 'error' => 'You already reviewed this order'], 422);
        }

        $pdo->prepare('INSERT INTO reviews (order_id, gig_id, buyer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$orderId, (int)$order['gig_id'], $uid, (int)$order['seller_id'], $rating, $comment]);
        recalc_ratings((int)$order['gig_id'], (int)$order['seller_id']);
        json_out(['ok' => true, 'message' => 'Review submitted']);
    }

    case 'request_payout': {
        if ($user['role'] !== 'seller') {
            json_out(['ok' => false, 'error' => 'Only Seller accounts can request withdrawals'], 403);
        }
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim((string)($_POST['method'] ?? 'Bank Transfer'));
        $accountInfo = trim((string)($_POST['account_info'] ?? ''));
        if ($amount < 10) {
            json_out(['ok' => false, 'error' => 'Minimum payout is $10'], 422);
        }
        if (mb_strlen($accountInfo) < 4) {
            json_out(['ok' => false, 'error' => 'Enter payout destination details'], 422);
        }

        $pdo->beginTransaction();
        try {
            $balStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
            $balStmt->execute([$uid]);
            $bal = (float)$balStmt->fetchColumn();
            if ($bal < $amount) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Insufficient balance'], 422);
            }
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?')->execute([$amount, $uid]);
            $pdo->prepare('INSERT INTO payout_requests (seller_id, amount, method, account_info) VALUES (?, ?, ?, ?)')
                ->execute([$uid, $amount, $method, $accountInfo]);
            $pdo->commit();
            json_out(['ok' => true, 'message' => 'Payout requested']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Payout request failed'], 500);
        }
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

