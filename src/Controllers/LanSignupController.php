<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Repositories\LanPlayerRepository;
use DuelDesk\Repositories\LanTeamMemberRepository;
use DuelDesk\Repositories\LanTeamRepository;
use DuelDesk\Services\LanEnrollmentService;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;

final class LanSignupController
{
    /** @param array<string, string> $params */
    public function signup(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        [$event, $slug] = $this->loadEventOr404($params);
        $ptype = $this->eventParticipantType($event);
        if ($ptype !== 'solo') {
            Response::badRequest('Not a solo LAN');
        }

        $this->assertDiscordLinkedUnlessAdmin();

        $me = Auth::user();
        $meId = Auth::id();
        if ($meId === null || !is_array($me)) {
            Response::badRequest('Not authenticated');
        }

        $handle = trim((string)($me['username'] ?? 'player'));
        if ($handle === '') {
            $handle = 'player';
        }

        try {
            $this->assertEventOpenForSignup($event, Auth::isAdmin());
            (new LanEnrollmentService())->registerSolo($event, $meId, $handle, Auth::isAdmin());
            Flash::set('success', 'Inscription LAN enregistree (auto-inscrit aux tournois).');
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage() !== '' ? $e->getMessage() : 'Inscription LAN impossible.');
        }

        Response::redirect('/lan/' . rawurlencode($slug));
    }

    /** @param array<string, string> $params */
    public function withdraw(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        [$event, $slug] = $this->loadEventOr404($params);
        $ptype = $this->eventParticipantType($event);
        if ($ptype !== 'solo') {
            Response::badRequest('Not a solo LAN');
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        try {
            (new LanEnrollmentService())->withdrawSolo($event, $meId, Auth::isAdmin());
            Flash::set('success', 'Retrait LAN enregistre.');
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage() !== '' ? $e->getMessage() : 'Retrait LAN impossible.');
        }

        Response::redirect('/lan/' . rawurlencode($slug));
    }

    /** @param array<string, string> $params */
    public function createTeam(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        [$event, $slug] = $this->loadEventOr404($params);
        $ptype = $this->eventParticipantType($event);
        if ($ptype !== 'team') {
            Response::badRequest('Not a team LAN');
        }

        $this->assertDiscordLinkedUnlessAdmin();

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $teamName = trim((string)($_POST['team_name'] ?? ''));
        if ($teamName === '' || $this->strlenSafe($teamName) < 2 || $this->strlenSafe($teamName) > 80) {
            Flash::set('error', "Nom d'equipe invalide (2 a 80).");
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            Response::badRequest('Invalid LAN');
        }

        $ltRepo = new LanTeamRepository();
        $existing = $ltRepo->findForUser($lanId, $meId);
        if (is_array($existing)) {
            Flash::set('error', 'Tu es deja dans une equipe LAN.');
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        try {
            $this->assertEventOpenForSignup($event, Auth::isAdmin());
            (new LanEnrollmentService())->createTeam($event, $meId, $teamName, Auth::isAdmin());
            Flash::set('success', 'Equipe LAN creee (auto-inscrite aux tournois).');
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage() !== '' ? $e->getMessage() : "Creation d'equipe impossible.");
        }

        Response::redirect('/lan/' . rawurlencode($slug));
    }

    /** @param array<string, string> $params */
    public function joinTeam(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        [$event, $slug] = $this->loadEventOr404($params);
        $ptype = $this->eventParticipantType($event);
        if ($ptype !== 'team') {
            Response::badRequest('Not a team LAN');
        }

        $this->assertDiscordLinkedUnlessAdmin();

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            Response::badRequest('Invalid LAN');
        }

        $ltRepo = new LanTeamRepository();
        $existing = $ltRepo->findForUser($lanId, $meId);
        if (is_array($existing)) {
            Flash::set('error', 'Tu es deja dans une equipe LAN.');
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        $code = strtoupper(trim((string)($_POST['join_code'] ?? '')));
        if ($code === '' || $this->strlenSafe($code) < 6 || $this->strlenSafe($code) > 16) {
            Flash::set('error', 'Code invalide.');
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        $team = $ltRepo->findByJoinCode($lanId, $code);
        if (!is_array($team)) {
            Flash::set('error', 'Equipe introuvable (code).');
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        $lanTeamId = (int)($team['id'] ?? 0);
        if ($lanTeamId <= 0) {
            Response::badRequest('Invalid team');
        }

        try {
            $this->assertEventOpenForSignup($event, Auth::isAdmin());
            (new LanEnrollmentService())->joinTeam($event, $meId, $lanTeamId, Auth::isAdmin());
            Flash::set('success', 'Tu as rejoint une equipe LAN (auto-inscrite aux tournois).');
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage() !== '' ? $e->getMessage() : "Impossible de rejoindre l'equipe.");
        }

        Response::redirect('/lan/' . rawurlencode($slug));
    }

    /** @param array<string, string> $params */
    public function leaveTeam(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        [$event, $slug] = $this->loadEventOr404($params);
        $ptype = $this->eventParticipantType($event);
        if ($ptype !== 'team') {
            Response::badRequest('Not a team LAN');
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $lanId = (int)($event['id'] ?? 0);
        $lanTeamId = (int)($params['teamId'] ?? 0);
        if ($lanId <= 0 || $lanTeamId <= 0) {
            Response::notFound();
        }

        $ltmRepo = new LanTeamMemberRepository();
        if (!$ltmRepo->isMember($lanTeamId, $meId) && !Auth::isAdmin()) {
            Flash::set('error', "Tu n'es pas dans cette equipe.");
            Response::redirect('/lan/' . rawurlencode($slug));
        }

        try {
            (new LanEnrollmentService())->leaveTeam($event, $meId, $lanTeamId, Auth::isAdmin());
            Flash::set('success', "Tu as quitte l'equipe LAN.");
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage() !== '' ? $e->getMessage() : "Impossible de quitter l'equipe.");
        }

        Response::redirect('/lan/' . rawurlencode($slug));
    }

    /**
     * @param array<string, string> $params
     * @return array{0:array<string,mixed>,1:string}
     */
    private function loadEventOr404(array $params): array
    {
        $slug = (string)($params['slug'] ?? '');
        if ($slug === '') {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findBySlug($slug);
        if (!is_array($event)) {
            Response::notFound();
        }

        return [$event, $slug];
    }

    /** @return 'solo'|'team' */
    private function eventParticipantType(array $event): string
    {
        $ptype = (string)($event['participant_type'] ?? 'solo');
        return in_array($ptype, ['solo', 'team'], true) ? $ptype : 'solo';
    }

    /** @param array<string,mixed> $event */
    private function assertEventOpenForSignup(array $event, bool $isAdmin): void
    {
        if ($isAdmin) {
            return;
        }

        $status = (string)($event['status'] ?? 'draft');
        if (!in_array($status, ['published', 'running'], true)) {
            throw new \RuntimeException('Inscriptions LAN fermees.');
        }
    }

    private function assertDiscordLinkedUnlessAdmin(): void
    {
        if (Auth::isAdmin()) {
            return;
        }

        $me = Auth::user();
        $discordUserId = is_array($me) ? trim((string)($me['discord_user_id'] ?? '')) : '';
        if ($discordUserId === '') {
            Flash::set('error', 'Connexion Discord requise: lie ton compte avant de rejoindre une LAN.');
            Response::redirect('/account');
        }
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
