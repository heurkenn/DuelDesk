<?php

declare(strict_types=1);

namespace DuelDesk\Support;

final class Csrf
{
    private const KEY = '__csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY]) || $_SESSION[self::KEY] === '') {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
