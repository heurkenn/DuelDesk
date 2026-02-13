<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\UserRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class AdminController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        Auth::requireAdmin();

        $uRepo = new UserRepository();
        $gRepo = new GameRepository();
        $tRepo = new TournamentRepository();

        $me = Auth::user();
        $myId = is_array($me) ? (int)($me['id'] ?? 0) : 0;

        View::render('admin/index', [
            'title' => 'Admin | DuelDesk',
            'stats' => [
                'users' => $uRepo->countAll(),
                'games' => $gRepo->countAll(),
                'tournaments' => $tRepo->countAll(),
                'my_tournaments' => $myId > 0 ? $tRepo->countByOwner($myId) : 0,
            ],
            'myTournaments' => $myId > 0 ? $tRepo->allByOwner($myId) : [],
        ]);
    }

    /** @param array<string, string> $params */
    public function users(array $params = []): void
    {
        Auth::requireAdmin();

        $repo = new UserRepository();
        $query = trim((string)($_GET['q'] ?? ''));
        $pageRaw = trim((string)($_GET['page'] ?? '1'));
        $page = (ctype_digit($pageRaw) && (int)$pageRaw > 0) ? (int)$pageRaw : 1;
        $perPage = 40;

        $total = $repo->countSearch($query);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        View::render('admin/users', [
            'title' => 'Utilisateurs | Admin | DuelDesk',
            'users' => $repo->searchPaged($query, $page, $perPage),
            'csrfToken' => Csrf::token(),
            'meId' => Auth::id() ?? 0,
            'adminCount' => $repo->countAdmins(),
            'query' => $query,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    /** @param array<string, string> $params */
    public function updateRole(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        $role = (string)($_POST['role'] ?? '');

        if ($id <= 0) {
            Response::badRequest('Invalid user id');
        }

        if (!in_array($role, ['user', 'admin'], true)) {
            Response::badRequest('Invalid role');
        }

        $repo = new UserRepository();
        $target = $repo->findById($id);
        if ($target === null) {
            Response::notFound();
        }

        $meId = Auth::id() ?? 0;
        if ($id === $meId && $role !== 'admin') {
            Flash::set('error', 'Tu ne peux pas retirer ton propre role admin.');
            Response::redirect('/admin/users');
        }

        if (($target['role'] ?? '') === 'admin' && $role !== 'admin') {
            $admins = $repo->countAdmins();
            if ($admins <= 1) {
                Flash::set('error', 'Impossible: il faut au moins 1 admin.');
                Response::redirect('/admin/users');
            }
        }

        $repo->setRole($id, $role);
        Flash::set('success', 'Role mis a jour.');
        Response::redirect('/admin/users');
    }
}
