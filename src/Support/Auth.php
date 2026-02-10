<?php

declare(strict_types=1);

namespace DuelDesk\Support;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\UserRepository;
use Throwable;

final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = self::id();
        if ($id === null) {
            return null;
        }

        try {
            $repo = new UserRepository();
            self::$cachedUser = $repo->findById($id);
            return self::$cachedUser;
        } catch (Throwable) {
            return null;
        }
    }

    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $id = (int)$id;
        return $id > 0 ? $id : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return is_array($u) && (($u['role'] ?? '') === 'admin');
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        $redirect = (string)($_SERVER['REQUEST_URI'] ?? '/');
        Flash::set('error', 'Connexion requise.');
        Response::redirect('/login?redirect=' . rawurlencode($redirect));
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            self::requireLogin();
        }

        if (!self::isAdmin()) {
            Response::forbidden('Acces reserve aux administrateurs.');
        }
    }
}
