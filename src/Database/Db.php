<?php

declare(strict_types=1);

namespace DuelDesk\Database;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'dueldesk';
        $user = getenv('DB_USER') ?: 'dueldesk';
        $pass = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Enable multi-statement migrations when supported.
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }
}
