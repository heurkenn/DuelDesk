<?php

declare(strict_types=1);

namespace DuelDesk\Support;

final class Flash
{
    private const KEY = '__flash';

    public static function set(string $type, string $message): void
    {
        $_SESSION[self::KEY][$type] = $message;
    }

    public static function get(string $type): ?string
    {
        if (!isset($_SESSION[self::KEY][$type])) {
            return null;
        }

        $msg = (string)$_SESSION[self::KEY][$type];
        unset($_SESSION[self::KEY][$type]);

        return $msg;
    }
}
