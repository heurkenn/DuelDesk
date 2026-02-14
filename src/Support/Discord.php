<?php

declare(strict_types=1);

namespace DuelDesk\Support;

final class Discord
{
    public static function avatarCdnUrl(string $discordUserId, string $discordAvatarHash, int $size = 64): ?string
    {
        $discordUserId = trim($discordUserId);
        $discordAvatarHash = trim($discordAvatarHash);

        if ($discordUserId === '' || $discordAvatarHash === '') {
            return null;
        }

        if (!ctype_digit($discordUserId)) {
            return null;
        }

        // Typical hash is 32 chars, sometimes animated: a_xxx.
        if (!preg_match('/^(?:a_)?[a-zA-Z0-9]{2,64}$/', $discordAvatarHash)) {
            return null;
        }

        if ($size < 16) {
            $size = 16;
        }
        if ($size > 256) {
            $size = 256;
        }

        $ext = str_starts_with($discordAvatarHash, 'a_') ? 'gif' : 'png';

        return 'https://cdn.discordapp.com/avatars/'
            . rawurlencode($discordUserId)
            . '/'
            . rawurlencode($discordAvatarHash)
            . '.'
            . $ext
            . '?size='
            . $size;
    }

    public static function botToken(): string
    {
        return trim((string)(getenv('DISCORD_BOT_TOKEN') ?: ''));
    }

    public static function guildId(): string
    {
        return trim((string)(getenv('DISCORD_GUILD_ID') ?: ''));
    }

    public static function participantRoleId(): string
    {
        return trim((string)(getenv('DISCORD_ROLE_ID_PARTICIPANT') ?: ''));
    }

    public static function webhookUrl(): string
    {
        return trim((string)(getenv('DISCORD_WEBHOOK_URL') ?: ''));
    }

    public static function isWebhookConfigured(): bool
    {
        $url = self::webhookUrl();
        return $url !== '' && self::isAllowedWebhookUrl($url);
    }

    public static function isBotConfigured(): bool
    {
        return self::botToken() !== '' && self::guildId() !== '' && self::participantRoleId() !== '';
    }

    public static function announce(string $content): bool
    {
        $url = self::webhookUrl();
        if ($url === '' || !self::isAllowedWebhookUrl($url)) {
            return false;
        }

        return self::postJson($url, [
            'content' => $content,
            'allowed_mentions' => [
                'parse' => [],
            ],
        ], expectNoContent: true);
    }

    public static function tryAutoRoleOnSignup(string $discordUserId): bool
    {
        $discordUserId = trim($discordUserId);
        if ($discordUserId === '') {
            return false;
        }

        $token = self::botToken();
        $guild = self::guildId();
        $role = self::participantRoleId();
        if ($token === '' || $guild === '' || $role === '') {
            return false;
        }

        $url = 'https://discord.com/api/guilds/' . rawurlencode($guild)
            . '/members/' . rawurlencode($discordUserId)
            . '/roles/' . rawurlencode($role);

        return self::request('PUT', $url, null, [
            'Authorization: Bot ' . $token,
        ], expectNoContent: true);
    }

    private static function isAllowedWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');

        if ($scheme !== 'https') {
            return false;
        }

        // Accept discord.com + legacy discordapp.com hosts.
        $allowedHosts = [
            'discord.com',
            'ptb.discord.com',
            'canary.discord.com',
            'discordapp.com',
        ];
        if (!in_array($host, $allowedHosts, true)) {
            return false;
        }

        return str_starts_with($path, '/api/webhooks/');
    }

    /** @param array<string, mixed> $payload */
    private static function postJson(string $url, array $payload, bool $expectNoContent = false): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return self::request('POST', $url, $json, [
            'Content-Type: application/json',
        ], $expectNoContent);
    }

    /** @param list<string> $headers */
    private static function request(string $method, string $url, ?string $body, array $headers, bool $expectNoContent = false): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($expectNoContent) {
            return $http === 204 || $http === 200;
        }

        return is_string($raw) && $raw !== '' && $http >= 200 && $http < 300;
    }
}
