<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        // Verify core table exists
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if (!$check) {
            bootstrap_database($pdo);
        }
        return $pdo;
    } catch (PDOException $e) {
        // Unknown database (1049) -> create and seed automatically
        if ((int)($e->errorInfo[1] ?? 0) === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
            $rootDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
            $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, $opts);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', DB_NAME);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $rootPdo->exec("USE `{$dbName}`");
            bootstrap_database($rootPdo);
            $pdo = $rootPdo;
            return $pdo;
        }
        throw $e;
    }
}

function bootstrap_database(PDO $pdo): void
{
    $schemaPath = BASE_PATH . '/database/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('Schema file missing: database/schema.sql');
    }

    $sql = file_get_contents($schemaPath);
    $lines = preg_split('/\R/', $sql);
    $clean = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $clean[] = $line;
    }
    $cleanSql = implode("\n", $clean);

    // Split on semicolon at statement boundaries outside quotes
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $len = strlen($cleanSql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $cleanSql[$i];
        $prev = $i > 0 ? $cleanSql[$i - 1] : '';
        if ($ch === "'" && !$inDouble && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && $prev !== '\\') {
            $inDouble = !$inDouble;
        }
        if ($ch === ';' && !$inSingle && !$inDouble) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        } else {
            $buffer .= $ch;
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    foreach ($statements as $stmt) {
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

