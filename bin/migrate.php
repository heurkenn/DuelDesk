<?php

declare(strict_types=1);

require __DIR__ . '/../src/Bootstrap.php';

use DuelDesk\Database\Db;

fwrite(STDOUT, "DuelDesk migrations\n");

$attempts = 30;
$pdo = null;
$lastError = null;

for ($i = 1; $i <= $attempts; $i++) {
    try {
        $pdo = Db::pdo();
        break;
    } catch (Throwable $e) {
        $lastError = $e;
        fwrite(STDERR, "Waiting for DB ({$i}/{$attempts})...\n");
        sleep(1);
    }
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB connection failed.\n");
    if ($lastError instanceof Throwable) {
        fwrite(STDERR, $lastError->getMessage() . "\n");
    }
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations ('
    . 'version VARCHAR(64) NOT NULL PRIMARY KEY,'
    . 'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = is_array($applied) ? array_map('strval', $applied) : [];

$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql');
if ($files === false) {
    fwrite(STDERR, "No migrations directory found: {$dir}\n");
    exit(1);
}

sort($files, SORT_STRING);

$available = array_map('basename', $files);
$available = is_array($available) ? array_values(array_map('strval', $available)) : [];

// If the DB was created with older "split" migrations that no longer exist on disk, abort.
// This repo now uses a squashed schema migration (001_schema.sql).
$missingOnDisk = array_values(array_diff($applied, $available));
if ($missingOnDisk !== []) {
    fwrite(STDERR, "Legacy DB detected: schema_migrations contains versions that are not present in database/migrations.\n");
    fwrite(STDERR, "This project now uses a squashed schema migration (database/migrations/001_schema.sql).\n");
    fwrite(STDERR, "Fix: wipe your Docker volumes (DB reset) and re-run migrations:\n");
    fwrite(STDERR, "  bin/dev.sh reset\n");
    fwrite(STDERR, "  bin/dev.sh up\n");
    exit(1);
}

$ran = 0;
foreach ($files as $file) {
    $version = basename($file);
    if (in_array($version, $applied, true)) {
        continue;
    }

    fwrite(STDOUT, "Applying {$version}...\n");
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read: {$file}\n");
        exit(1);
    }

    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:v)');
        $stmt->execute(['v' => $version]);
        $ran++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration failed: {$version}\n");
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

fwrite(STDOUT, $ran === 0 ? "Nothing to do.\n" : "Done. Applied {$ran} migration(s).\n");
