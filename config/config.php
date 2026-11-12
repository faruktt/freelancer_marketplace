<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DB_HOST', getenv('KRAFTR_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('KRAFTR_DB_PORT') ?: '3306');
define('DB_NAME', getenv('KRAFTR_DB_NAME') ?: 'faruk_dbb');
define('DB_USER', getenv('KRAFTR_DB_USER') ?: 'root');
define('DB_PASS', getenv('KRAFTR_DB_PASS') !== false ? getenv('KRAFTR_DB_PASS') : '');

define('UPLOAD_MAX_IMAGE_BYTES', 4 * 1024 * 1024);
define('UPLOAD_MAX_FILE_BYTES', 15 * 1024 * 1024);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

