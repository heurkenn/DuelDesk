<?php

declare(strict_types=1);

namespace DuelDesk\Support;

use DuelDesk\Database\Db;
use PDO;
use Throwable;

final class RateLimit
{
    public static function tooManyAttempts(string $key, int $maxHits, int $windowSeconds): bool
    {
        $key = self::normalizeKey($key);
        if ($key === '' || $maxHits <= 0 || $windowSeconds <= 0) {
            return false;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT hits, reset_at FROM rate_limits WHERE k = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return false;
        }

        $resetAt = (string)($row['reset_at'] ?? '');
        $resetTs = $resetAt !== '' ? (int)strtotime($resetAt . ' UTC') : 0;
        if ($resetTs <= 0) {
            return false;
        }

        $now = time();
        if ($resetTs <= $now) {
            return false;
        }

        return ((int)($row['hits'] ?? 0)) >= $maxHits;
    }

    public static function hit(string $key, int $windowSeconds, int $inc = 1): void
    {
        $key = self::normalizeKey($key);
        if ($key === '' || $windowSeconds <= 0 || $inc <= 0) {
            return;
        }

        $pdo = Db::pdo();
        $now = time();
        $resetAt = gmdate('Y-m-d H:i:s', $now + $windowSeconds);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT hits, reset_at FROM rate_limits WHERE k = :k LIMIT 1 FOR UPDATE');
            $stmt->execute(['k' => $key]);
            $row = $stmt->fetch();

            if (!is_array($row)) {
                $ins = $pdo->prepare('INSERT INTO rate_limits (k, hits, reset_at) VALUES (:k, :hits, :reset_at)');
                $ins->execute(['k' => $key, 'hits' => $inc, 'reset_at' => $resetAt]);
                $pdo->commit();
                return;
            }

            $oldResetAt = (string)($row['reset_at'] ?? '');
            $oldResetTs = $oldResetAt !== '' ? (int)strtotime($oldResetAt . ' UTC') : 0;

            if ($oldResetTs <= 0 || $oldResetTs <= $now) {
                $upd = $pdo->prepare('UPDATE rate_limits SET hits = :hits, reset_at = :reset_at WHERE k = :k');
                $upd->execute(['k' => $key, 'hits' => $inc, 'reset_at' => $resetAt]);
                $pdo->commit();
                return;
            }

            $hits = (int)($row['hits'] ?? 0) + $inc;
            $upd = $pdo->prepare('UPDATE rate_limits SET hits = :hits WHERE k = :k');
            $upd->execute(['k' => $key, 'hits' => $hits]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function clear(string $key): void
    {
        $key = self::normalizeKey($key);
        if ($key === '') {
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE k = :k');
        $stmt->execute(['k' => $key]);
    }

    private static function normalizeKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        // Hard cap to avoid unbounded growth and keep primary key size reasonable.
        if (strlen($key) > 180) {
            $key = substr($key, 0, 120) . ':' . hash('sha256', $key);
        }

        return $key;
    }

    public static function ip(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}

