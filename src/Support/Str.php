<?php

declare(strict_types=1);

namespace DuelDesk\Support;

final class Str
{
    public static function slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'tournament';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'tournament';
    }
}
