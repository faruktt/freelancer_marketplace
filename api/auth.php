<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';
require_csrf();

switch ($action) {
    case 'login': {
        $login = trim((string)($_POST['login'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $expectedRole = trim((string)($_POST['expected_role'] ?? ''));
        if ($login === '' || $pass === '') {
            json_out(['ok' => false, 'error' => 'Email and password are required'], 422);
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            json_out(['ok' => false, 'error' => 'Invalid credentials'], 401);
        }
        if ($user['status'] !== 'active') {
            json_out(['ok' => false, 'error' => 'Account is ' . $user['status'] . '. Contact support.'], 403);
        }
        if ($expectedRole !== '' && in_array($expectedRole, ['admin', 'buyer', 'seller'], true) && $user['role'] !== $expectedRole) {
            json_out(['ok' => false, 'error' => 'This account is a ' . ucfirst($user['role']) . '. Please click the ' . ucfirst($user['role']) . ' button above.'], 403);
        }
        login_user((int)$user['id']);
        $redirect = panel_url($user);
        json_out(['ok' => true, 'redirect' => $redirect, 'role' => $user['role']]);
    }

    case 'register': {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'buyer') === 'seller' ? 'seller' : 'buyer';

        if (mb_strlen($name) < 2) {
            json_out(['ok' => false, 'error' => 'Enter your full name'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(['ok' => false, 'error' => 'Enter a valid email address'], 422);
        }
        if (strlen($pass) < 6) {
            json_out(['ok' => false, 'error' => 'Password must be at least 6 characters'], 422);
        }

        $pdo = db();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            json_out(['ok' => false, 'error' => 'Email already registered'], 409);
        }

        $baseUser = slugify(explode('@', $email)[0]);
        $username = $baseUser;
        $i = 1;
        $uChk = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        while (true) {
            $uChk->execute([$username]);
            if (!$uChk->fetchColumn()) {
                break;
            }
            $username = $baseUser . $i++;
        }

        $avatarPath = null;
        if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload($_FILES['avatar'], 'avatars', 'image');
            if (!$up['ok']) {
                json_out(['ok' => false, 'error' => $up['error']], 422);
            }
            $avatarPath = $up['path'];
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role, avatar, title, wallet_balance) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00)');
        $ins->execute([
            $name,
            $username,
            $email,
            $hash,
            $role,
            $avatarPath,
            $role === 'seller' ? 'Independent Freelancer' : 'Marketplace Client',
        ]);
        login_user((int)$pdo->lastInsertId());
        json_out(['ok' => true, 'redirect' => panel_url(['role' => $role])]);
    }

    case 'logout': {
        logout_user();
        json_out(['ok' => true, 'redirect' => 'index.php']);
    }

    case 'topup': {
        $user = require_role('buyer', 'login.php', true);
        $amount = (float)($_POST['amount'] ?? 250);
        if ($amount < 10 || $amount > 5000) {
            json_out(['ok' => false, 'error' => 'Amount must be between $10 and $5000'], 422);
        }
        db()->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')
            ->execute([$amount, (int)$user['id']]);
        $newBal = (float)db()->query('SELECT wallet_balance FROM users WHERE id = ' . (int)$user['id'])->fetchColumn();
        json_out(['ok' => true, 'wallet_balance' => $newBal, 'formatted' => money($newBal)]);
    }

    case 'update_profile': {
        $user = require_login(true);
        $name = trim((string)($_POST['name'] ?? $user['name']));
        $title = trim((string)($_POST['title'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $skills = trim((string)($_POST['skills'] ?? ''));
        $country = trim((string)($_POST['country'] ?? 'Remote'));
        $newPass = (string)($_POST['new_password'] ?? '');

        if (mb_strlen($name) < 2) {
            json_out(['ok' => false, 'error' => 'Name cannot be empty'], 422);
        }
        if ($newPass !== '' && strlen($newPass) < 6) {
            json_out(['ok' => false, 'error' => 'New password must be at least 6 characters'], 422);
        }

        $avatarPath = $user['avatar'];
        if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload($_FILES['avatar'], 'avatars', 'image');
            if (!$up['ok']) {
                json_out(['ok' => false, 'error' => $up['error']], 422);
            }
            $avatarPath = $up['path'];
        }

        db()->prepare('UPDATE users SET name = ?, title = ?, bio = ?, skills = ?, country = ?, avatar = ? WHERE id = ?')
            ->execute([$name, $title, $bio, $skills, $country, $avatarPath, (int)$user['id']]);
        if ($newPass !== '') {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_BCRYPT), (int)$user['id']]);
        }
        json_out(['ok' => true, 'message' => 'Profile updated']);
    }

    case 'upload_avatar': {
        $user = require_login(true);
        if (empty($_FILES['avatar'])) {
            json_out(['ok' => false, 'error' => 'Select an image file'], 422);
        }
        $up = handle_upload($_FILES['avatar'], 'avatars', 'image');
        if (!$up['ok']) {
            json_out(['ok' => false, 'error' => $up['error']], 422);
        }
        db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$up['path'], (int)$user['id']]);
        json_out(['ok' => true, 'avatar' => $up['path'], 'message' => 'Profile photo updated']);
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

