<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class AdminLanEventController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        Auth::requireAdmin();

        $repo = new LanEventRepository();
        $events = $repo->all();

        View::render('admin/lan_events', [
            'title' => 'LAN | Admin | DuelDesk',
            'events' => $events,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function new(array $params = []): void
    {
        Auth::requireAdmin();

        View::render('admin/lan_event_edit', [
            'title' => 'Nouveau LAN | Admin | DuelDesk',
            'isNew' => true,
            'event' => null,
            'tournaments' => [],
            'availableTournaments' => [],
            'old' => [
                'name' => '',
                'status' => 'draft',
                'starts_at' => '',
                'ends_at' => '',
                'location' => '',
                'description' => '',
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $built = $this->buildFromPost();
        if ($built['errors'] !== []) {
            View::render('admin/lan_event_edit', [
                'title' => 'Nouveau LAN | Admin | DuelDesk',
                'isNew' => true,
                'event' => null,
                'tournaments' => [],
                'availableTournaments' => [],
                'old' => $built['old'],
                'errors' => $built['errors'],
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        $repo = new LanEventRepository();
        $id = $repo->create(
            Auth::id(),
            $built['name'],
            $built['status'],
            $built['startsAt'],
            $built['endsAt'],
            $built['location'],
            $built['description']
        );

        Flash::set('success', 'LAN cree.');
        Response::redirect('/admin/lan/' . $id);
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        Auth::requireAdmin();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findById($id);
        if ($event === null) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($id);
        $available = $tRepo->listUnassignedForLan();

        View::render('admin/lan_event_edit', [
            'title' => 'Edit LAN | Admin | DuelDesk',
            'isNew' => false,
            'event' => $event,
            'tournaments' => $tournaments,
            'availableTournaments' => $available,
            'old' => [
                'name' => (string)($event['name'] ?? ''),
                'status' => (string)($event['status'] ?? 'draft'),
                'starts_at' => $this->toDatetimeLocal($event['starts_at'] ?? null),
                'ends_at' => $this->toDatetimeLocal($event['ends_at'] ?? null),
                'location' => is_string($event['location'] ?? null) ? (string)$event['location'] : '',
                'description' => is_string($event['description'] ?? null) ? (string)$event['description'] : '',
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findById($id);
        if ($event === null) {
            Response::notFound();
        }

        $built = $this->buildFromPost();
        if ($built['errors'] !== []) {
            $tRepo = new TournamentRepository();
            $tournaments = $tRepo->listByLanEventId($id);
            $available = $tRepo->listUnassignedForLan();

            View::render('admin/lan_event_edit', [
                'title' => 'Edit LAN | Admin | DuelDesk',
                'isNew' => false,
                'event' => $event,
                'tournaments' => $tournaments,
                'availableTournaments' => $available,
                'old' => $built['old'],
                'errors' => $built['errors'],
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        $repo->update(
            $id,
            $built['name'],
            $built['status'],
            $built['startsAt'],
            $built['endsAt'],
            $built['location'],
            $built['description']
        );

        Flash::set('success', 'LAN mis a jour.');
        Response::redirect('/admin/lan/' . $id);
    }

    /** @param array<string, string> $params */
    public function attachTournament(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findById($id);
        if ($event === null) {
            Response::notFound();
        }

        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            Flash::set('error', 'Tournoi invalide.');
            Response::redirect('/admin/lan/' . $id);
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Flash::set('error', 'Tournoi introuvable.');
            Response::redirect('/admin/lan/' . $id);
        }

        if (($t['lan_event_id'] ?? null) !== null) {
            Flash::set('error', 'Ce tournoi est deja dans un LAN.');
            Response::redirect('/admin/lan/' . $id);
        }

        $tRepo->updateLanEvent($tournamentId, $id);

        Flash::set('success', 'Tournoi ajoute au LAN.');
        Response::redirect('/admin/lan/' . $id);
    }

    /** @param array<string, string> $params */
    public function detachTournament(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        $tournamentId = (int)($params['tournamentId'] ?? 0);
        if ($id <= 0 || $tournamentId <= 0) {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findById($id);
        if ($event === null) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Flash::set('error', 'Tournoi introuvable.');
            Response::redirect('/admin/lan/' . $id);
        }

        $current = $t['lan_event_id'] ?? null;
        $current = (is_int($current) || is_string($current)) ? (int)$current : 0;
        if ($current !== $id) {
            Flash::set('error', 'Ce tournoi ne fait pas partie de ce LAN.');
            Response::redirect('/admin/lan/' . $id);
        }

        $tRepo->updateLanEvent($tournamentId, null);

        Flash::set('success', 'Tournoi retire du LAN.');
        Response::redirect('/admin/lan/' . $id);
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findById($id);
        if ($event === null) {
            Response::notFound();
        }

        // Ensure detach even if the DB doesn't have a FK constraint yet.
        $tRepo = new TournamentRepository();
        $tRepo->clearLanEvent($id);

        $repo->delete($id);

        Flash::set('success', 'LAN supprime.');
        Response::redirect('/admin/lan');
    }

    /**
     * @return array{
     *   errors: array<string, string>,
     *   old: array<string, string>,
     *   name: string,
     *   status: string,
     *   startsAt: ?string,
     *   endsAt: ?string,
     *   location: ?string,
     *   description: ?string
     * }
     */
    private function buildFromPost(): array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $startsAtRaw = (string)($_POST['starts_at'] ?? '');
        $endsAtRaw = (string)($_POST['ends_at'] ?? '');
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $old = [
            'name' => $name,
            'status' => $status,
            'starts_at' => $startsAtRaw,
            'ends_at' => $endsAtRaw,
            'location' => $location,
            'description' => $description,
        ];

        $errors = [];
        if ($name === '' || $this->strlenSafe($name) > 120) {
            $errors['name'] = 'Nom requis (max 120).';
        }

        $allowedStatuses = ['draft', 'published', 'running', 'completed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $errors['status'] = 'Statut invalide.';
        }

        $startsAt = $this->normalizeDatetimeLocal($startsAtRaw);
        if ($startsAtRaw !== '' && $startsAt === null) {
            $errors['starts_at'] = 'Date de debut invalide.';
        }

        $endsAt = $this->normalizeDatetimeLocal($endsAtRaw);
        if ($endsAtRaw !== '' && $endsAt === null) {
            $errors['ends_at'] = 'Date de fin invalide.';
        }

        if ($startsAt !== null && $endsAt !== null) {
            $s = strtotime($startsAt);
            $e = strtotime($endsAt);
            if ($s !== false && $e !== false && $e < $s) {
                $errors['ends_at'] = 'Date de fin doit etre apres le debut.';
            }
        }

        if ($location !== '' && $this->strlenSafe($location) > 160) {
            $errors['location'] = 'Lieu trop long (max 160).';
        }

        if ($description !== '' && $this->strlenSafe($description) > 8000) {
            $errors['description'] = 'Description trop longue (max 8000).';
        }

        return [
            'errors' => $errors,
            'old' => $old,
            'name' => $name,
            'status' => $status,
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'location' => $location !== '' ? $location : null,
            'description' => $description !== '' ? $description : null,
        ];
    }

    private function normalizeDatetimeLocal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $value)) {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        return $value . ':00';
    }

    private function toDatetimeLocal(mixed $dbValue): string
    {
        if (!is_string($dbValue) || $dbValue === '') {
            return '';
        }

        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $dbValue)) {
            return '';
        }

        $v = substr($dbValue, 0, 16);
        if ($v === false) {
            return '';
        }

        return str_replace(' ', 'T', $v);
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}

