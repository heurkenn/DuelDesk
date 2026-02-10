<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TeamRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;

final class TournamentTeamController
{
    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        if (($t['participant_type'] ?? 'solo') !== 'team') {
            Response::badRequest('Not a team tournament');
        }

        $status = (string)($t['status'] ?? 'draft');
        $isOpen = in_array($status, ['published', 'running'], true);
        if (!$isOpen && !Auth::isAdmin()) {
            Flash::set('error', 'Inscriptions fermees.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $teamName = trim((string)($_POST['team_name'] ?? ''));
        if ($teamName === '' || $this->strlenSafe($teamName) < 2 || $this->strlenSafe($teamName) > 80) {
            Flash::set('error', "Nom d'equipe invalide (2 a 80).");
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $existingTeam = $tmRepo->findTeamForUserInTournament($tournamentId, $meId);
        if ($existingTeam !== null) {
            Flash::set('error', 'Tu es deja dans une equipe.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $teamRepo = new TeamRepository();
        $slug = $teamRepo->uniqueSlug($tournamentId, $teamName);
        $joinCode = $teamRepo->generateUniqueJoinCode(10);

        $teamId = $teamRepo->create($tournamentId, $teamName, $slug, $joinCode, $meId);
        $tmRepo->addMember($teamId, $meId, 'captain');

        $ttRepo = new TournamentTeamRepository();
        $ttRepo->add($tournamentId, $teamId);

        Flash::set('success', 'Equipe creee. Partage le code pour que tes mates rejoignent.');
        Response::redirect('/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function join(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        if (($t['participant_type'] ?? 'solo') !== 'team') {
            Response::badRequest('Not a team tournament');
        }

        $status = (string)($t['status'] ?? 'draft');
        $isOpen = in_array($status, ['published', 'running'], true);
        if (!$isOpen && !Auth::isAdmin()) {
            Flash::set('error', 'Inscriptions fermees.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $code = strtoupper(trim((string)($_POST['join_code'] ?? '')));
        if ($code === '' || $this->strlenSafe($code) < 6 || $this->strlenSafe($code) > 16) {
            Flash::set('error', 'Code invalide.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $existingTeam = $tmRepo->findTeamForUserInTournament($tournamentId, $meId);
        if ($existingTeam !== null) {
            Flash::set('error', 'Tu es deja dans une equipe.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findByJoinCode($tournamentId, $code);
        if ($team === null) {
            Flash::set('error', 'Equipe introuvable (code).');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $teamId = (int)($team['id'] ?? 0);
        if ($teamId <= 0) {
            Response::badRequest('Invalid team');
        }

        $teamSize = (int)($t['team_size'] ?? 0);
        if ($teamSize < 2) {
            $teamSize = 2;
        }

        $count = $tmRepo->countMembers($teamId);
        if ($count >= $teamSize) {
            Flash::set('error', "Equipe pleine ({$teamSize}).");
            Response::redirect('/tournaments/' . $tournamentId);
        }

        // Ensure the team is registered as an entrant (idempotent).
        $ttRepo = new TournamentTeamRepository();
        $ttRepo->add($tournamentId, $teamId);

        $tmRepo->addMember($teamId, $meId, 'member');

        Flash::set('success', 'Tu as rejoint une equipe.');
        Response::redirect('/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function leave(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $teamId = (int)($params['teamId'] ?? 0);
        if ($tournamentId <= 0 || $teamId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        if (($t['participant_type'] ?? 'solo') !== 'team') {
            Response::badRequest('Not a team tournament');
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null || (int)($team['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $tmRepo = new TeamMemberRepository();
        if (!$tmRepo->isUserInTeam($teamId, $meId)) {
            Flash::set('error', "Tu n'es pas dans cette equipe.");
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $captainId = $tmRepo->captainUserId($teamId);
        $wasCaptain = $captainId !== null && $captainId === $meId;

        $tmRepo->removeMember($teamId, $meId);

        $remaining = $tmRepo->countMembers($teamId);
        if ($remaining <= 0) {
            // Delete team (cascade removes tournament_teams + memberships).
            $teamRepo->delete($teamId);
            Flash::set('success', 'Equipe supprimee.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        if ($wasCaptain) {
            $tmRepo->promoteOldestMemberToCaptain($teamId);
        }

        Flash::set('success', "Tu as quitte l'equipe.");
        Response::redirect('/tournaments/' . $tournamentId);
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
