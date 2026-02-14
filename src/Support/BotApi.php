<?php

declare(strict_types=1);

namespace DuelDesk\Support;

final class BotApi
{
    public static function token(): string
    {
        return trim((string)(getenv('BOT_API_TOKEN') ?: ''));
    }

    public static function isConfigured(): bool
    {
        return self::token() !== '';
    }

    public static function requireAuth(): void
    {
        $expected = self::token();
        if ($expected === '') {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Bot API not configured'], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $token = '';
        if (preg_match('/^Bearer\\s+(.+)$/i', trim($auth), $m)) {
            $token = trim((string)($m[1] ?? ''));
        }

        if ($token === '' || !hash_equals($expected, $token)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

