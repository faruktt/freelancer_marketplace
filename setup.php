<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = db();
    $counts = [
        'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'gigs' => (int)$pdo->query('SELECT COUNT(*) FROM gigs')->fetchColumn(),
        'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'messages' => (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    ];
    if (PHP_SAPI === 'cli') {
        echo "OK: " . json_encode($counts) . PHP_EOL;
    } else {
        header('Location: index.php');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup error: ' . e($e->getMessage());
}

