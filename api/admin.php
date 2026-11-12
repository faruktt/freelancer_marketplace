<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

require_csrf();
$admin = require_admin(true);
$adminId = (int)$admin['id'];
$action = $_POST['action'] ?? '';
$pdo = db();

switch ($action) {
    case 'user_update': {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid <= 0) {
            json_out(['ok' => false, 'error' => 'Invalid user'], 422);
        }
        $role = in_array($_POST['role'] ?? '', ['buyer', 'seller', 'admin'], true) ? $_POST['role'] : 'buyer';
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended', 'banned'], true) ? $_POST['status'] : 'active';
        $level = in_array($_POST['seller_level'] ?? '', ['new', 'rising', 'top_rated', 'pro'], true) ? $_POST['seller_level'] : 'new';
        $verifiedCheckbox = !empty($_POST['is_verified']);
        if ($level === 'pro' || $verifiedCheckbox) {
            $level = 'pro';
            $verified = 1;
        } else {
            $verified = 0;
        }
        $wallet = $role === 'admin' ? 0.00 : max(0, (float)($_POST['wallet_balance'] ?? 0));

        if ($uid === $adminId && ($role !== 'admin' || $status !== 'active')) {
            json_out(['ok' => false, 'error' => 'Cannot demote or suspend yourself'], 422);
        }

        $pdo->prepare('UPDATE users SET role = ?, status = ?, seller_level = ?, is_verified = ?, wallet_balance = ? WHERE id = ?')
            ->execute([$role, $status, $level, $verified, $wallet, $uid]);
        json_out(['ok' => true, 'message' => 'User updated']);
    }

    case 'user_create': {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['buyer', 'seller', 'admin'], true) ? $_POST['role'] : 'buyer';
        $wallet = $role === 'admin' ? 0.00 : max(0, (float)($_POST['wallet_balance'] ?? 0));

        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(['ok' => false, 'error' => 'Valid name and email required'], 422);
        }
        if (strlen($pass) < 6) {
            json_out(['ok' => false, 'error' => 'Password must be at least 6 characters'], 422);
        }
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            json_out(['ok' => false, 'error' => 'Email already exists'], 409);
        }

        $username = slugify(explode('@', $email)[0]) . random_int(10, 99);
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $avatar = null;
        $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role, avatar, wallet_balance, is_verified, seller_level) VALUES (?, ?, ?, ?, ?, ?, ?, 0, "new")')
            ->execute([$name, $username, $email, $hash, $role, $avatar, $wallet]);
        json_out(['ok' => true, 'message' => 'User created']);
    }

    case 'user_status': {
        $uid = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended', 'banned'], true) ? $_POST['status'] : 'active';
        if ($uid === $adminId && $status !== 'active') {
            json_out(['ok' => false, 'error' => 'Cannot deactivate your own admin account'], 422);
        }
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $uid]);
        json_out(['ok' => true, 'message' => 'User status updated to ' . ($status === 'active' ? 'Active' : 'Inactive')]);
    }

    case 'user_delete': {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === $adminId) {
            json_out(['ok' => false, 'error' => 'Cannot delete your own admin account'], 422);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        json_out(['ok' => true, 'message' => 'User deleted']);
    }

    case 'gig_status': {
        $gigId = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'paused', 'pending', 'rejected'], true)) {
            json_out(['ok' => false, 'error' => 'Invalid status'], 422);
        }
        $pdo->prepare('UPDATE gigs SET status = ? WHERE id = ?')->execute([$status, $gigId]);
        json_out(['ok' => true, 'message' => 'Gig status updated to ' . ($status === 'active' ? 'Active' : 'Inactive (' . ucfirst($status) . ')')]);
    }

    case 'gig_feature': {
        $gigId = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE gigs SET is_featured = 1 - is_featured WHERE id = ?')->execute([$gigId]);
        $val = (int)$pdo->query('SELECT is_featured FROM gigs WHERE id = ' . $gigId)->fetchColumn();
        json_out(['ok' => true, 'is_featured' => $val]);
    }

    case 'gig_delete': {
        $gigId = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM gigs WHERE id = ?')->execute([$gigId]);
        json_out(['ok' => true, 'message' => 'Gig deleted']);
    }

    case 'gig_update': {
        $gigId = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 0);
        $price = max(1, (float)($_POST['price'] ?? 10));
        $days = max(1, (int)($_POST['delivery_days'] ?? 3));
        $status = in_array($_POST['status'] ?? '', ['active', 'paused', 'pending', 'rejected'], true) ? $_POST['status'] : 'active';
        $featured = !empty($_POST['is_featured']) ? 1 : 0;

        if ($gigId <= 0 || mb_strlen($title) < 6 || $catId <= 0) {
            json_out(['ok' => false, 'error' => 'Valid gig title and category required'], 422);
        }
        $pdo->prepare('UPDATE gigs SET title = ?, category_id = ?, price = ?, delivery_days = ?, status = ?, is_featured = ? WHERE id = ?')
            ->execute([$title, $catId, $price, $days, $status, $featured, $gigId]);
        json_out(['ok' => true, 'message' => 'Gig updated']);
    }

    case 'order_resolve': {
        $orderId = (int)($_POST['id'] ?? 0);
        $resolution = $_POST['resolution'] ?? '';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Order not found'], 404);
            }

            if ($resolution === 'force_complete') {
                if ($order['status'] === 'completed' || $order['status'] === 'cancelled') {
                    $pdo->rollBack();
                    json_out(['ok' => false, 'error' => 'Order already finalized'], 422);
                }
                $pdo->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
                $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ?, earnings_total = earnings_total + ? WHERE id = ?')
                    ->execute([(float)$order['seller_net'], (float)$order['seller_net'], (int)$order['seller_id']]);
                $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'admin_complete', 'Admin finalized order and released payout to seller.')")
                    ->execute([$orderId, $adminId]);
            } elseif ($resolution === 'refund_cancel') {
                if ($order['status'] === 'completed' || $order['status'] === 'cancelled') {
                    $pdo->rollBack();
                    json_out(['ok' => false, 'error' => 'Order already finalized'], 422);
                }
                $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
                $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')
                    ->execute([(float)$order['total_paid'], (int)$order['buyer_id']]);
                $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'admin_refund', 'Admin cancelled order and refunded buyer wallet.')")
                    ->execute([$orderId, $adminId]);
            } elseif (in_array($resolution, ['pending', 'in_progress', 'delivered', 'disputed'], true)) {
                $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$resolution, $orderId]);
                $pdo->prepare("INSERT INTO order_events (order_id, actor_id, event_type, note) VALUES (?, ?, 'admin_status', ?)")
                    ->execute([$orderId, $adminId, 'Admin set status to ' . $resolution]);
            } else {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Invalid action'], 422);
            }
            $pdo->commit();
            json_out(['ok' => true, 'message' => 'Order updated']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Order action failed'], 500);
        }
    }

    case 'order_delete': {
        $orderId = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
        json_out(['ok' => true, 'message' => 'Order removed']);
    }

    case 'category_save': {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $icon = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)($_POST['icon'] ?? 'category')))) ?: 'category';
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['accent_color'] ?? '')) ? $_POST['accent_color'] : '#4F46E5';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($name) < 2) {
            json_out(['ok' => false, 'error' => 'Category name required'], 422);
        }
        $slug = slugify($name);

        if ($id > 0) {
            $pdo->prepare('UPDATE categories SET name = ?, slug = ?, icon = ?, accent_color = ?, sort_order = ?, is_active = ? WHERE id = ?')
                ->execute([$name, $slug, $icon, $color, $sortOrder, $active, $id]);
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, icon, accent_color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $slug . '-' . random_int(10, 99), $icon, $color, $sortOrder, $active]);
        }
        json_out(['ok' => true, 'message' => 'Category saved']);
    }

    case 'category_delete': {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = (int)$pdo->query('SELECT COUNT(*) FROM gigs WHERE category_id = ' . $id)->fetchColumn();
        if ($cnt > 0) {
            json_out(['ok' => false, 'error' => "Cannot delete: {$cnt} gig(s) use this category. Reassign or hide instead."], 422);
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        json_out(['ok' => true, 'message' => 'Category deleted']);
    }

    case 'review_toggle': {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT gig_id, seller_id, is_visible FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if (!$rev) {
            json_out(['ok' => false, 'error' => 'Review not found'], 404);
        }
        $next = $rev['is_visible'] ? 0 : 1;
        $pdo->prepare('UPDATE reviews SET is_visible = ? WHERE id = ?')->execute([$next, $id]);
        recalc_ratings((int)$rev['gig_id'], (int)$rev['seller_id']);
        json_out(['ok' => true, 'is_visible' => $next]);
    }

    case 'review_delete': {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT gig_id, seller_id FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if (!$rev) {
            json_out(['ok' => false, 'error' => 'Review not found'], 404);
        }
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        recalc_ratings((int)$rev['gig_id'], (int)$rev['seller_id']);
        json_out(['ok' => true, 'message' => 'Review deleted']);
    }

    case 'message_delete': {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
        json_out(['ok' => true, 'message' => 'Message removed']);
    }

    case 'payout_action': {
        $id = (int)($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM payout_requests WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p || $p['status'] !== 'pending') {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Payout request unavailable'], 422);
            }
            if ($decision === 'approve') {
                $pdo->prepare("UPDATE payout_requests SET status = 'approved' WHERE id = ?")->execute([$id]);
            } elseif ($decision === 'reject') {
                $pdo->prepare("UPDATE payout_requests SET status = 'rejected' WHERE id = ?")->execute([$id]);
                $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')
                    ->execute([(float)$p['amount'], (int)$p['seller_id']]);
            } else {
                $pdo->rollBack();
                json_out(['ok' => false, 'error' => 'Invalid decision'], 422);
            }
            $pdo->commit();
            json_out(['ok' => true, 'message' => 'Payout ' . $decision . 'd']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_out(['ok' => false, 'error' => 'Payout action failed'], 500);
        }
    }

    case 'settings_save': {
        $current = get_settings();
        $allowed = [
            'site_name' => trim((string)($_POST['site_name'] ?? 'Kraftr')) ?: 'Kraftr',
            'logo_show_name' => !empty($_POST['logo_show_name']) ? '1' : '0',
            'commission_pct' => (string)max(0, min(50, (int)($_POST['commission_pct'] ?? 10))),
            'auto_approve_gigs' => !empty($_POST['auto_approve_gigs']) ? '1' : '0',
            'min_gig_price' => (string)max(1, (int)($_POST['min_gig_price'] ?? 5)),
            'max_gig_price' => (string)max(10, (int)($_POST['max_gig_price'] ?? 10000)),
            'announcement' => trim((string)($_POST['announcement'] ?? '')),
        ];

        $unlinkBranding = static function (string $path): void {
            if ($path !== '' && str_starts_with($path, 'uploads/branding/') && !str_contains($path, '..')) {
                $full = BASE_PATH . '/' . $path;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        };

        $brandingFields = [
            'site_logo' => ['mode' => 'image', 'remove' => 'remove_logo'],
            'site_favicon' => ['mode' => 'icon', 'remove' => 'remove_favicon'],
        ];
        foreach ($brandingFields as $field => $cfg) {
            $prev = $current[$field] ?? '';
            if (!empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = handle_upload($_FILES[$field], 'branding', $cfg['mode']);
                if (!$up['ok']) {
                    json_out(['ok' => false, 'error' => $up['error']], 422);
                }
                $unlinkBranding($prev);
                $allowed[$field] = $up['path'];
            } elseif (!empty($_POST[$cfg['remove']])) {
                $unlinkBranding($prev);
                $allowed[$field] = '';
            }
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($allowed as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        json_out([
            'ok' => true,
            'message' => 'Settings saved',
            'site_logo' => $allowed['site_logo'] ?? ($current['site_logo'] ?? ''),
            'site_favicon' => $allowed['site_favicon'] ?? ($current['site_favicon'] ?? ''),
        ]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

