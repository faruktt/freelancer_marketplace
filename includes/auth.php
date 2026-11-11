<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token = null): bool
{
    $candidate = $token ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    return is_string($candidate) && $candidate !== '' && hash_equals(csrf_token(), $candidate);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        json_out(['ok' => false, 'error' => 'Invalid security token. Please refresh.'], 403);
    }
}

function current_user(bool $refresh = false): ?array
{
    static $cached = false;
    if (!$refresh && $cached !== false) {
        return $cached;
    }

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        $cached = null;
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, username, email, role, status, avatar, title, bio, skills, country, is_verified, seller_level, wallet_balance, earnings_total, rating_avg, review_count, last_seen_at, created_at FROM users WHERE id = ?');
    $stmt->execute([(int)$uid]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        $cached = null;
        return null;
    }

    $now = time();
    if (empty($_SESSION['last_ping']) || ($now - (int)$_SESSION['last_ping']) > 60) {
        db()->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        $_SESSION['last_ping'] = $now;
    }

    $cached = $user;
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['last_ping'] = time();
    db()->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?')->execute([$userId]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function panel_url(?array $user = null): string
{
    $role = $user['role'] ?? 'buyer';
    return match ($role) {
        'admin' => 'admin-panel.php',
        'seller' => 'seller-panel.php',
        default => 'buyer-panel.php',
    };
}

function require_login(bool $isApi = false, string $loginUrl = 'index.php?auth=login'): array
{
    $user = current_user();
    if (!$user) {
        if ($isApi) {
            json_out(['ok' => false, 'error' => 'Authentication required', 'code' => 'UNAUTHENTICATED'], 401);
        }
        header('Location: ' . $loginUrl);
        exit;
    }
    return $user;
}

function require_role(string $role, string $loginUrl = 'index.php?auth=login', bool $isApi = false): array
{
    $user = require_login($isApi, $loginUrl);
    if ($user['role'] !== $role) {
        if ($isApi) {
            json_out(['ok' => false, 'error' => ucfirst($role) . ' access required'], 403);
        }
        header('Location: ' . panel_url($user));
        exit;
    }
    return $user;
}

function require_admin(bool $isApi = false): array
{
    return require_role('admin', 'admin-login.php', $isApi);
}

