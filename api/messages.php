<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login(true);
$uid = (int)$user['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'threads';

    if ($action === 'unread') {
        json_out(['ok' => true, 'unread' => get_unread_count($uid)]);
    }

    if ($action === 'threads') {
        $stmt = $pdo->prepare('
            SELECT c.id, c.gig_id, c.order_id, c.last_message_at,
                   CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END AS peer_id,
                   u.name AS peer_name, u.username AS peer_username, u.avatar AS peer_avatar,
                   u.role AS peer_role, u.title AS peer_title, u.last_seen_at AS peer_seen,
                   g.title AS gig_title, o.order_code,
                   (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                   (SELECT attachment_name FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_attachment,
                   (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id <> ? AND m.is_read = 0) AS unread_count
            FROM conversations c
            JOIN users u ON u.id = (CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END)
            LEFT JOIN gigs g ON g.id = c.gig_id
            LEFT JOIN orders o ON o.id = c.order_id
            WHERE c.user_one_id = ? OR c.user_two_id = ?
            ORDER BY c.last_message_at DESC
        ');
        $stmt->execute([$uid, $uid, $uid, $uid, $uid]);
        $threads = $stmt->fetchAll();

        $now = time();
        foreach ($threads as &$t) {
            $t['is_online'] = $t['peer_seen'] && ($now - strtotime($t['peer_seen'])) < 180;
            $t['time_ago'] = time_ago($t['last_message_at']);
            $t['unread_count'] = (int)$t['unread_count'];
        }

        json_out(['ok' => true, 'threads' => $threads, 'total_unread' => get_unread_count($uid)]);
    }

    if ($action === 'messages') {
        $convId = (int)($_GET['conversation_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);

        $chk = $pdo->prepare('SELECT * FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)');
        $chk->execute([$convId, $uid, $uid]);
        $conv = $chk->fetch();
        if (!$conv) {
            json_out(['ok' => false, 'error' => 'Conversation not found'], 404);
        }

        $pdo->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0')
            ->execute([$convId, $uid]);

        $sql = 'SELECT m.*, u.name AS sender_name, u.avatar AS sender_avatar, g.title AS gig_title, o.order_code
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                LEFT JOIN gigs g ON g.id = m.gig_id
                LEFT JOIN orders o ON o.id = m.order_id
                WHERE m.conversation_id = ?';
        $params = [$convId];
        if ($afterId > 0) {
            $sql .= ' AND m.id > ?';
            $params[] = $afterId;
        }
        $sql .= ' ORDER BY m.id ASC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $msgs = $stmt->fetchAll();

        foreach ($msgs as &$m) {
            $m['is_mine'] = (int)$m['sender_id'] === $uid;
            $m['time_label'] = date('g:i A', strtotime($m['created_at']));
        }

        json_out(['ok' => true, 'messages' => $msgs, 'total_unread' => get_unread_count($uid)]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

require_csrf();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start': {
        $recipientId = (int)($_POST['recipient_id'] ?? 0);
        $gigId = !empty($_POST['gig_id']) ? (int)$_POST['gig_id'] : null;
        $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
        $body = trim((string)($_POST['body'] ?? ''));

        if ($recipientId <= 0 || $recipientId === $uid) {
            json_out(['ok' => false, 'error' => 'Invalid recipient'], 422);
        }

        $uChk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
        $uChk->execute([$recipientId]);
        if (!$uChk->fetchColumn()) {
            json_out(['ok' => false, 'error' => 'User unavailable'], 404);
        }

        $convId = find_or_create_conversation($uid, $recipientId, $gigId, $orderId);
        if ($body !== '') {
            $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, gig_id, order_id, body) VALUES (?, ?, ?, ?, ?)')
                ->execute([$convId, $uid, $gigId, $orderId, $body]);
            $pdo->prepare('UPDATE conversations SET last_message_at = NOW(), gig_id = COALESCE(?, gig_id), order_id = COALESCE(?, order_id) WHERE id = ?')
                ->execute([$gigId, $orderId, $convId]);
        }

        json_out(['ok' => true, 'conversation_id' => $convId, 'redirect' => 'messages.php?c=' . $convId]);
    }

    case 'send': {
        $convId = (int)($_POST['conversation_id'] ?? 0);
        $body = trim((string)($_POST['body'] ?? ''));
        $gigId = !empty($_POST['gig_id']) ? (int)$_POST['gig_id'] : null;
        $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;

        $chk = $pdo->prepare('SELECT * FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)');
        $chk->execute([$convId, $uid, $uid]);
        $conv = $chk->fetch();
        if (!$conv) {
            json_out(['ok' => false, 'error' => 'Conversation not found'], 404);
        }

        $attPath = null;
        $attName = null;
        if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload($_FILES['attachment'], 'attachments', 'file');
            if (!$up['ok']) {
                json_out(['ok' => false, 'error' => $up['error']], 422);
            }
            $attPath = $up['path'];
            $attName = $up['name'];
        }

        if ($body === '' && !$attPath) {
            json_out(['ok' => false, 'error' => 'Enter a message or attach a file'], 422);
        }

        $ins = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, gig_id, order_id, body, attachment, attachment_name) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$convId, $uid, $gigId, $orderId, $body !== '' ? $body : ('Sent file: ' . $attName), $attPath, $attName]);
        $msgId = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = ?')->execute([$convId]);

        $mStmt = $pdo->prepare('SELECT m.*, u.name AS sender_name, u.avatar AS sender_avatar FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?');
        $mStmt->execute([$msgId]);
        $msg = $mStmt->fetch();
        $msg['is_mine'] = true;
        $msg['time_label'] = date('g:i A', strtotime($msg['created_at']));

        json_out(['ok' => true, 'message' => $msg]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

