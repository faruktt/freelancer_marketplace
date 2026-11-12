<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function money(float|int|string $amount): string
{
    return '$' . number_format((float)$amount, 2);
}

function money_short(float|int|string $amount): string
{
    $f = (float)$amount;
    return fmod($f, 1.0) === 0.0 ? '$' . number_format($f, 0) : '$' . number_format($f, 2);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('M j', $ts);
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? substr($slug, 0, 120) : 'item';
}

function get_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $cache = [];
    foreach ($rows as $row) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $cache;
}

function get_setting(string $key, string $default = ''): string
{
    $settings = get_settings();
    return $settings[$key] ?? $default;
}

/**
 * Resolve a stored branding asset path to a cache-busted URL if the file exists locally.
 */
function branding_asset_url(string $settingKey, bool $withVersion = true): string
{
    $path = trim(get_setting($settingKey, ''));
    if ($path === '' || str_contains($path, '..')) {
        return '';
    }
    $full = BASE_PATH . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        return '';
    }
    return $withVersion ? ($path . '?v=' . filemtime($full)) : $path;
}

function avatar_url(?string $path): string
{
    $p = trim((string)$path);
    if ($p !== '' && !str_contains($p, '..') && is_file(BASE_PATH . '/' . ltrim($p, '/'))) {
        return $p;
    }
    return 'assets/images/default-avatar.svg';
}

function handle_upload(array $file, string $subdir, string $mode = 'image'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'empty' => true, 'error' => 'No file selected'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . (int)$file['error'] . ')'];
    }

    $maxBytes = $mode === 'image' ? UPLOAD_MAX_IMAGE_BYTES : UPLOAD_MAX_FILE_BYTES;
    if ((int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File exceeds size limit (' . round($maxBytes / 1048576) . 'MB)'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $imageMap = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
    $iconMap = $imageMap + [
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico', 'image/png'],
    ];
    $fileMap = $imageMap + [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
    ];

    $allowed = match ($mode) {
        'image' => $imageMap,
        'icon' => $iconMap,
        default => $fileMap,
    };
    if (!isset($allowed[$ext])) {
        return ['ok' => false, 'error' => 'Unsupported file format: .' . $ext];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowed[$ext], true)) {
        return ['ok' => false, 'error' => 'Invalid file MIME type'];
    }

    $safeSubdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir);
    $targetDir = BASE_PATH . '/uploads/' . $safeSubdir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $filename = bin2hex(random_bytes(10)) . '.' . $ext;
    $dest = $targetDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save uploaded file'];
    }

    return [
        'ok' => true,
        'path' => 'uploads/' . $safeSubdir . '/' . $filename,
        'name' => basename((string)$file['name']),
    ];
}

function recalc_ratings(int $gigId, int $sellerId): void
{
    $pdo = db();
    $gStmt = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE gig_id = ? AND is_visible = 1');
    $gStmt->execute([$gigId]);
    $gRow = $gStmt->fetch();
    $pdo->prepare('UPDATE gigs SET rating_avg = ?, review_count = ? WHERE id = ?')
        ->execute([round((float)$gRow['avg_r'], 2), (int)$gRow['cnt'], $gigId]);

    $sStmt = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE seller_id = ? AND is_visible = 1');
    $sStmt->execute([$sellerId]);
    $sRow = $sStmt->fetch();
    $pdo->prepare('UPDATE users SET rating_avg = ?, review_count = ? WHERE id = ?')
        ->execute([round((float)$sRow['avg_r'], 2), (int)$sRow['cnt'], $sellerId]);
}

function get_unread_count(int $userId): int
{
    $stmt = db()->prepare('
        SELECT COUNT(*) FROM messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE (c.user_one_id = ? OR c.user_two_id = ?)
          AND m.sender_id <> ?
          AND m.is_read = 0
    ');
    $stmt->execute([$userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

function find_or_create_conversation(int $u1, int $u2, ?int $gigId = null, ?int $orderId = null): int
{
    $a = min($u1, $u2);
    $b = max($u1, $u2);
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?');
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();

    if ($id) {
        if ($gigId || $orderId) {
            $pdo->prepare('UPDATE conversations SET gig_id = COALESCE(?, gig_id), order_id = COALESCE(?, order_id) WHERE id = ?')
                ->execute([$gigId, $orderId, (int)$id]);
        }
        return (int)$id;
    }

    $ins = $pdo->prepare('INSERT INTO conversations (user_one_id, user_two_id, gig_id, order_id, last_message_at) VALUES (?, ?, ?, ?, NOW())');
    $ins->execute([$a, $b, $gigId, $orderId]);
    return (int)$pdo->lastInsertId();
}

function status_meta(string $status): array
{
    return match ($status) {
        'pending' => ['Pending', 'badge-amber', 'schedule'],
        'in_progress' => ['In Progress', 'badge-indigo', 'bolt'],
        'delivered' => ['Delivered', 'badge-cyan', 'inventory_2'],
        'revision' => ['Revision', 'badge-orange', 'autorenew'],
        'completed' => ['Completed', 'badge-emerald', 'check_circle'],
        'cancelled' => ['Cancelled', 'badge-slate', 'cancel'],
        'disputed' => ['Disputed', 'badge-rose', 'gavel'],
        'active' => ['Active', 'badge-emerald', 'check_circle'],
        'paused' => ['Paused', 'badge-slate', 'pause_circle'],
        'rejected' => ['Rejected', 'badge-rose', 'block'],
        'suspended' => ['Suspended', 'badge-amber', 'pause_circle'],
        'banned' => ['Banned', 'badge-rose', 'block'],
        'approved' => ['Approved', 'badge-emerald', 'check_circle'],
        default => [ucfirst($status), 'badge-slate', 'info'],
    };
}

function seller_level_label(string $level): string
{
    return match ($level) {
        'pro' => 'Pro Verified',
        'top_rated' => 'Top Rated',
        'rising' => 'Rising Talent',
        default => 'Seller',
    };
}

