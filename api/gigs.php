<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare('SELECT g.*, c.name AS category_name, c.slug AS category_slug FROM gigs g JOIN categories c ON c.id = g.category_id WHERE g.id = ?');
        $stmt->execute([$id]);
        $gig = $stmt->fetch();
        if (!$gig) {
            json_out(['ok' => false, 'error' => 'Gig not found'], 404);
        }
        json_out(['ok' => true, 'gig' => $gig]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
    $delivery = isset($_GET['delivery']) && $_GET['delivery'] !== '' ? (int)$_GET['delivery'] : null;
    $minRating = isset($_GET['rating']) && $_GET['rating'] !== '' ? (float)$_GET['rating'] : null;
    $sort = $_GET['sort'] ?? 'recommended';

    $where = ["g.status = 'active'", "u.status = 'active'", 'c.is_active = 1'];
    $params = [];

    if ($q !== '') {
        $where[] = '(g.title LIKE ? OR g.tags LIKE ? OR g.description LIKE ? OR u.name LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($category !== '' && $category !== 'all') {
        $where[] = 'c.slug = ?';
        $params[] = $category;
    }
    if ($maxPrice !== null && $maxPrice > 0) {
        $where[] = 'g.price <= ?';
        $params[] = $maxPrice;
    }
    if ($delivery !== null && $delivery > 0) {
        $where[] = 'g.delivery_days <= ?';
        $params[] = $delivery;
    }
    if ($minRating !== null && $minRating > 0) {
        $where[] = 'g.rating_avg >= ?';
        $params[] = $minRating;
    }

    $orderBy = match ($sort) {
        'price_asc' => 'g.price ASC, g.rating_avg DESC',
        'price_desc' => 'g.price DESC, g.rating_avg DESC',
        'rating' => 'g.rating_avg DESC, g.review_count DESC',
        'newest' => 'g.created_at DESC',
        default => 'g.is_featured DESC, g.orders_count DESC, g.rating_avg DESC',
    };

    $sql = '
        SELECT g.id, g.title, g.slug, g.price, g.delivery_days, g.revisions, g.cover_image,
               g.is_featured, g.rating_avg, g.review_count, g.orders_count,
               c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.accent_color,
               u.id AS seller_id, u.name AS seller_name, u.username AS seller_username,
               u.avatar AS seller_avatar, u.seller_level, u.is_verified
        FROM gigs g
        JOIN categories c ON c.id = g.category_id
        JOIN users u ON u.id = g.seller_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy . '
        LIMIT 60
    ';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $gigs = $stmt->fetchAll();

    $user = current_user();
    $favIds = [];
    if ($user) {
        $fStmt = db()->prepare('SELECT gig_id FROM favorites WHERE user_id = ?');
        $fStmt->execute([(int)$user['id']]);
        $favIds = array_map('intval', $fStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    foreach ($gigs as &$g) {
        $g['is_fav'] = in_array((int)$g['id'], $favIds, true);
    }

    json_out(['ok' => true, 'gigs' => $gigs, 'count' => count($gigs)]);
}

if ($method !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

require_csrf();
$user = require_login(true);
$action = $_POST['action'] ?? '';
$pdo = db();

switch ($action) {
    case 'toggle_favorite': {
        $gigId = (int)($_POST['gig_id'] ?? 0);
        if ($gigId <= 0) {
            json_out(['ok' => false, 'error' => 'Invalid gig'], 422);
        }
        $chk = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND gig_id = ?');
        $chk->execute([(int)$user['id'], $gigId]);
        if ($chk->fetchColumn()) {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND gig_id = ?')->execute([(int)$user['id'], $gigId]);
            json_out(['ok' => true, 'favorited' => false]);
        } else {
            $pdo->prepare('INSERT INTO favorites (user_id, gig_id) VALUES (?, ?)')->execute([(int)$user['id'], $gigId]);
            json_out(['ok' => true, 'favorited' => true]);
        }
    }

    case 'create':
    case 'update': {
        if ($user['role'] !== 'seller') {
            json_out(['ok' => false, 'error' => 'Only Seller accounts can create or edit gigs'], 403);
        }

        $gigId = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $deliveryDays = max(1, min(90, (int)($_POST['delivery_days'] ?? 3)));
        $revisions = max(0, min(20, (int)($_POST['revisions'] ?? 2)));
        $description = trim((string)($_POST['description'] ?? ''));
        $features = trim((string)($_POST['features'] ?? ''));
        $tags = trim((string)($_POST['tags'] ?? ''));
        $presetCover = trim((string)($_POST['cover_preset'] ?? ''));

        $minPrice = (float)get_setting('min_gig_price', '5');
        $maxPrice = (float)get_setting('max_gig_price', '10000');

        if (mb_strlen($title) < 8) {
            json_out(['ok' => false, 'error' => 'Title must be at least 8 characters'], 422);
        }
        if ($catId <= 0) {
            json_out(['ok' => false, 'error' => 'Select a valid category'], 422);
        }
        if ($price < $minPrice || $price > $maxPrice) {
            json_out(['ok' => false, 'error' => "Price must be between \${$minPrice} and \${$maxPrice}"], 422);
        }
        if (mb_strlen($description) < 20) {
            json_out(['ok' => false, 'error' => 'Description must be at least 20 characters'], 422);
        }

        $coverPath = null;
        if (!empty($_FILES['cover_image']) && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload($_FILES['cover_image'], 'gigs', 'image');
            if (!$up['ok']) {
                json_out(['ok' => false, 'error' => $up['error']], 422);
            }
            $coverPath = $up['path'];
        } elseif ($presetCover !== '' && preg_match('#^assets/images/gig-\d+\.jpg$#', $presetCover)) {
            $coverPath = $presetCover;
        }

        if ($action === 'create') {
            if (!$coverPath) {
                $coverPath = 'assets/images/gig-' . random_int(1, 12) . '.jpg';
            }
            $baseSlug = slugify($title);
            $slug = $baseSlug . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
            $autoApprove = get_setting('auto_approve_gigs', '0') === '1';
            $status = $autoApprove ? 'active' : 'pending';

            $ins = $pdo->prepare('INSERT INTO gigs (seller_id, category_id, title, slug, description, price, delivery_days, revisions, features, tags, cover_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([(int)$user['id'], $catId, $title, $slug, $description, $price, $deliveryDays, $revisions, $features, $tags, $coverPath, $status]);
            $newId = (int)$pdo->lastInsertId();
            json_out([
                'ok' => true,
                'id' => $newId,
                'status' => $status,
                'message' => $status === 'active' ? 'Gig published live!' : 'Gig submitted for admin approval.',
            ]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM gigs WHERE id = ?');
            $stmt->execute([$gigId]);
            $existing = $stmt->fetch();
            if (!$existing) {
                json_out(['ok' => false, 'error' => 'Gig not found'], 404);
            }
            if ((int)$existing['seller_id'] !== (int)$user['id']) {
                json_out(['ok' => false, 'error' => 'Permission denied'], 403);
            }
            $finalCover = $coverPath ?: $existing['cover_image'];
            $upd = $pdo->prepare('UPDATE gigs SET category_id = ?, title = ?, description = ?, price = ?, delivery_days = ?, revisions = ?, features = ?, tags = ?, cover_image = ? WHERE id = ?');
            $upd->execute([$catId, $title, $description, $price, $deliveryDays, $revisions, $features, $tags, $finalCover, $gigId]);
            json_out(['ok' => true, 'message' => 'Gig updated']);
        }
    }

    case 'toggle_status': {
        if ($user['role'] !== 'seller') {
            json_out(['ok' => false, 'error' => 'Only Seller accounts can manage gigs'], 403);
        }
        $gigId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, seller_id, status FROM gigs WHERE id = ?');
        $stmt->execute([$gigId]);
        $gig = $stmt->fetch();
        if (!$gig) {
            json_out(['ok' => false, 'error' => 'Gig not found'], 404);
        }
        if ((int)$gig['seller_id'] !== (int)$user['id']) {
            json_out(['ok' => false, 'error' => 'Permission denied'], 403);
        }
        if (!in_array($gig['status'], ['active', 'paused'], true)) {
            json_out(['ok' => false, 'error' => 'Gig cannot be toggled from ' . $gig['status']], 422);
        }
        $next = $gig['status'] === 'active' ? 'paused' : 'active';
        $pdo->prepare('UPDATE gigs SET status = ? WHERE id = ?')->execute([$next, $gigId]);
        json_out(['ok' => true, 'status' => $next]);
    }

    case 'delete': {
        if ($user['role'] !== 'seller') {
            json_out(['ok' => false, 'error' => 'Only Seller accounts can manage gigs'], 403);
        }
        $gigId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, seller_id FROM gigs WHERE id = ?');
        $stmt->execute([$gigId]);
        $gig = $stmt->fetch();
        if (!$gig) {
            json_out(['ok' => false, 'error' => 'Gig not found'], 404);
        }
        if ((int)$gig['seller_id'] !== (int)$user['id']) {
            json_out(['ok' => false, 'error' => 'Permission denied'], 403);
        }
        $pdo->prepare('DELETE FROM gigs WHERE id = ?')->execute([$gigId]);
        json_out(['ok' => true, 'message' => 'Gig deleted']);
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

