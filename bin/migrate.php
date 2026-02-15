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

// Post-migrate hardening / compatibility:
// When using a squashed schema, CREATE TABLE IF NOT EXISTS won't evolve existing columns.
// Apply minimal ALTERs for forward-compatible upgrades.
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $type = is_array($col) ? (string)($col['Type'] ?? '') : '';
    if ($type !== '' && !str_contains($type, 'super_admin')) {
        fwrite(STDOUT, "Upgrading users.role enum to include super_admin...\n");
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");
    }
} catch (Throwable) {
    // Best-effort: ignore if table doesn't exist yet or permissions are restricted.
}

// LAN registrations tables (introduced after the initial schema squash).
try {
    $exists = $pdo->query("SHOW TABLES LIKE 'lan_players'")->fetchColumn();
    if (!$exists) {
        fwrite(STDOUT, "Creating LAN registration tables...\n");
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS lan_players ("
            . " lan_event_id BIGINT UNSIGNED NOT NULL,"
            . " user_id BIGINT UNSIGNED NOT NULL,"
            . " joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " PRIMARY KEY (lan_event_id, user_id),"
            . " KEY idx_lan_players_user_id (user_id),"
            . " CONSTRAINT fk_lan_players_event FOREIGN KEY (lan_event_id) REFERENCES lan_events(id) ON DELETE CASCADE,"
            . " CONSTRAINT fk_lan_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS lan_teams ("
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
            . " lan_event_id BIGINT UNSIGNED NOT NULL,"
            . " name VARCHAR(80) NOT NULL,"
            . " slug VARCHAR(120) NOT NULL,"
            . " join_code CHAR(10) NOT NULL,"
            . " created_by_user_id BIGINT UNSIGNED NULL,"
            . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " PRIMARY KEY (id),"
            . " UNIQUE KEY uniq_lan_teams_join_code (join_code),"
            . " UNIQUE KEY uniq_lan_teams_event_slug (lan_event_id, slug),"
            . " KEY idx_lan_teams_event_id (lan_event_id),"
            . " KEY idx_lan_teams_created_by (created_by_user_id),"
            . " CONSTRAINT fk_lan_teams_event FOREIGN KEY (lan_event_id) REFERENCES lan_events(id) ON DELETE CASCADE,"
            . " CONSTRAINT fk_lan_teams_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS lan_team_members ("
            . " lan_team_id BIGINT UNSIGNED NOT NULL,"
            . " user_id BIGINT UNSIGNED NOT NULL,"
            . " role ENUM('captain','member') NOT NULL DEFAULT 'member',"
            . " joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " PRIMARY KEY (lan_team_id, user_id),"
            . " KEY idx_lan_team_members_user_id (user_id),"
            . " CONSTRAINT fk_lan_team_members_team FOREIGN KEY (lan_team_id) REFERENCES lan_teams(id) ON DELETE CASCADE,"
            . " CONSTRAINT fk_lan_team_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS lan_team_tournament_teams ("
            . " lan_team_id BIGINT UNSIGNED NOT NULL,"
            . " tournament_id BIGINT UNSIGNED NOT NULL,"
            . " team_id BIGINT UNSIGNED NOT NULL,"
            . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " PRIMARY KEY (lan_team_id, tournament_id),"
            . " UNIQUE KEY uniq_ltt_team_id (team_id),"
            . " KEY idx_ltt_tournament_id (tournament_id),"
            . " CONSTRAINT fk_ltt_lan_team FOREIGN KEY (lan_team_id) REFERENCES lan_teams(id) ON DELETE CASCADE,"
            . " CONSTRAINT fk_ltt_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,"
            . " CONSTRAINT fk_ltt_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
} catch (Throwable) {
    // Best-effort.
}

fwrite(STDOUT, $ran === 0 ? "Nothing to do.\n" : "Done. Applied {$ran} migration(s).\n");
