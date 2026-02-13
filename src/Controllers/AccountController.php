<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\UserRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class AccountController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        Auth::requireLogin();

        $me = Auth::user();
        if (!is_array($me)) {
            Response::forbidden('Connexion requise.');
        }

        View::render('account/index', [
            'title' => 'Compte | DuelDesk',
            'me' => $me,
            'csrfToken' => Csrf::token(),
            'discordConfigured' => $this->discordConfigured(),
        ]);
    }

    /** @param array<string, string> $params */
    public function discordConnect(array $params = []): void
    {
        Auth::requireLogin();

        $cfg = $this->discordConfig();
        if ($cfg === null) {
            Flash::set('error', 'Discord non configure (env DISCORD_CLIENT_ID/SECRET/REDIRECT_URI).');
            Response::redirect('/account');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['discord_oauth_state'] = $state;
        $_SESSION['discord_oauth_started_at'] = time();

        $qs = http_build_query([
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

        Response::redirect('https://discord.com/api/oauth2/authorize?' . $qs);
    }

    /** @param array<string, string> $params */
    public function discordCallback(array $params = []): void
    {
        Auth::requireLogin();

        $cfg = $this->discordConfig();
        if ($cfg === null) {
            Flash::set('error', 'Discord non configure.');
            Response::redirect('/account');
        }

        $code = trim((string)($_GET['code'] ?? ''));
        $state = trim((string)($_GET['state'] ?? ''));
        $error = trim((string)($_GET['error'] ?? ''));

        if ($error !== '') {
            Flash::set('error', 'Discord: autorisation refusee.');
            Response::redirect('/account');
        }

        $expected = is_string($_SESSION['discord_oauth_state'] ?? null) ? (string)$_SESSION['discord_oauth_state'] : '';
        $startedAt = is_int($_SESSION['discord_oauth_started_at'] ?? null) ? (int)$_SESSION['discord_oauth_started_at'] : 0;
        unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_started_at']);

        if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
            Flash::set('error', 'Discord: state invalide, recommence.');
            Response::redirect('/account');
        }

        if ($startedAt > 0 && (time() - $startedAt) > 10 * 60) {
            Flash::set('error', 'Discord: session expiree, recommence.');
            Response::redirect('/account');
        }

        if ($code === '') {
            Flash::set('error', 'Discord: code manquant.');
            Response::redirect('/account');
        }

        $token = $this->discordExchangeCode($cfg, $code);
        if (!is_array($token) || ($token['access_token'] ?? '') === '') {
            Flash::set('error', 'Discord: echec recuperation token.');
            Response::redirect('/account');
        }

        $accessToken = (string)$token['access_token'];
        $me = $this->discordMe($accessToken);
        if (!is_array($me) || ($me['id'] ?? '') === '') {
            Flash::set('error', 'Discord: echec recuperation profil.');
            Response::redirect('/account');
        }

        $discordId = (string)$me['id'];
        $discordUsername = is_string($me['username'] ?? null) ? (string)$me['username'] : '';
        $discordGlobalName = is_string($me['global_name'] ?? null) ? (string)$me['global_name'] : '';
        $discordAvatar = is_string($me['avatar'] ?? null) ? (string)$me['avatar'] : '';

        $userId = Auth::id();
        if ($userId === null) {
            Response::forbidden('Connexion requise.');
        }

        $repo = new UserRepository();

        // Prevent linking the same Discord account to multiple DuelDesk accounts.
        $existing = $repo->findByDiscordUserId($discordId);
        if (is_array($existing) && (int)($existing['id'] ?? 0) !== (int)$userId) {
            Flash::set('error', 'Ce compte Discord est deja lie a un autre compte DuelDesk.');
            Response::redirect('/account');
        }

        $repo->linkDiscord((int)$userId, $discordId, $discordUsername, $discordGlobalName, $discordAvatar);
        Flash::set('success', 'Compte Discord lie.');
        Response::redirect('/account');
    }

    /** @param array<string, string> $params */
    public function discordDisconnect(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $userId = Auth::id();
        if ($userId === null) {
            Response::forbidden('Connexion requise.');
        }

        $repo = new UserRepository();
        $repo->unlinkDiscord((int)$userId);

        Flash::set('success', 'Discord deconnecte.');
        Response::redirect('/account');
    }

    private function discordConfigured(): bool
    {
        return $this->discordConfig() !== null;
    }

    /** @return array{client_id:string,client_secret:string,redirect_uri:string}|null */
    private function discordConfig(): ?array
    {
        $cid = trim((string)(getenv('DISCORD_CLIENT_ID') ?: ''));
        $secret = trim((string)(getenv('DISCORD_CLIENT_SECRET') ?: ''));
        $redirect = trim((string)(getenv('DISCORD_REDIRECT_URI') ?: ''));

        if ($redirect === '') {
            $appUrl = trim((string)(getenv('APP_URL') ?: ''));
            if ($appUrl !== '') {
                $redirect = rtrim($appUrl, '/') . '/account/discord/callback';
            }
        }

        if ($cid === '' || $secret === '' || $redirect === '') {
            return null;
        }

        return [
            'client_id' => $cid,
            'client_secret' => $secret,
            'redirect_uri' => $redirect,
        ];
    }

    /** @param array{client_id:string,client_secret:string,redirect_uri:string} $cfg */
    private function discordExchangeCode(array $cfg, string $code): ?array
    {
        $ch = curl_init('https://discord.com/api/oauth2/token');
        if ($ch === false) {
            return null;
        }

        $post = http_build_query([
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
        ], '', '&', PHP_QUERY_RFC3986);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $http < 200 || $http >= 300) {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    private function discordMe(string $accessToken): ?array
    {
        $ch = curl_init('https://discord.com/api/users/@me');
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $http < 200 || $http >= 300) {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}

