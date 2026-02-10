<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TeamRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class TeamController
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $teamId = (int)($params['id'] ?? 0);
        if ($teamId <= 0) {
            Response::notFound();
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null) {
            Response::notFound();
        }

        $tournamentId = (int)($team['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournament = $tRepo->findById($tournamentId);
        if ($tournament === null) {
            Response::notFound();
        }

        $tmRepo = new TeamMemberRepository();
        $members = $tmRepo->listMembers($teamId);

        $isMember = false;
        $myRole = '';
        $joinCode = '';
        $canManage = false;
        $rosterLocked = false;

        if (Auth::check()) {
            $meId = Auth::id();
            if ($meId !== null) {
                $isMember = $tmRepo->isUserInTeam($teamId, $meId);
                if ($isMember) {
                    $joinCode = (string)($team['join_code'] ?? '');
                    foreach ($members as $m) {
                        if ((int)($m['user_id'] ?? 0) === $meId) {
                            $myRole = (string)($m['role'] ?? '');
                            break;
                        }
                    }
                }
            }
        }

        $canManage = Auth::isAdmin() || ($isMember && $myRole === 'captain');
        $rosterLocked = $canManage && !Auth::isAdmin() && $this->isRosterLocked($tournament);

        View::render('teams/show', [
            'title' => ((string)($team['name'] ?? 'Equipe')) . ' | DuelDesk',
            'team' => $team,
            'tournament' => $tournament,
            'members' => $members,
            'isMember' => $isMember,
            'myRole' => $myRole,
            'joinCode' => $joinCode,
            'canManage' => $canManage,
            'rosterLocked' => $rosterLocked,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function rename(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $teamId = (int)($params['id'] ?? 0);
        if ($teamId <= 0) {
            Response::notFound();
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null) {
            Response::notFound();
        }

        $tournamentId = (int)($team['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournament = $tRepo->findById($tournamentId);
        if ($tournament === null) {
            Response::notFound();
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $isCaptain = ($tmRepo->captainUserId($teamId) ?? 0) === $meId;

        if (!Auth::isAdmin() && !$isCaptain) {
            Flash::set('error', "Acces refuse (capitaine requis).");
            Response::redirect('/teams/' . $teamId);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || $this->strlenSafe($name) < 2 || $this->strlenSafe($name) > 80) {
            Flash::set('error', "Nom d'equipe invalide (2 a 80).");
            Response::redirect('/teams/' . $teamId);
        }

        $teamRepo->updateName($teamId, $name);

        Flash::set('success', 'Equipe renomme.');
        Response::redirect('/teams/' . $teamId);
    }

    /** @param array<string, string> $params */
    public function kick(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $teamId = (int)($params['id'] ?? 0);
        $userId = (int)($params['userId'] ?? 0);
        if ($teamId <= 0 || $userId <= 0) {
            Response::notFound();
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null) {
            Response::notFound();
        }

        $tournamentId = (int)($team['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournament = $tRepo->findById($tournamentId);
        if ($tournament === null) {
            Response::notFound();
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $isCaptain = ($tmRepo->captainUserId($teamId) ?? 0) === $meId;
        $isAdmin = Auth::isAdmin();

        if (!$isAdmin && !$isCaptain) {
            Flash::set('error', "Acces refuse (capitaine requis).");
            Response::redirect('/teams/' . $teamId);
        }

        if (!$isAdmin && $this->isRosterLocked($tournament)) {
            Flash::set('error', 'Roster verrouille (inscriptions fermees ou bracket genere).');
            Response::redirect('/teams/' . $teamId);
        }

        if ($userId === $meId) {
            Flash::set('error', "Tu ne peux pas te kick toi-meme.");
            Response::redirect('/teams/' . $teamId);
        }

        $captainId = $tmRepo->captainUserId($teamId);
        if ($captainId !== null && $userId === $captainId) {
            Flash::set('error', "Impossible de kick le capitaine. Transfere le role avant.");
            Response::redirect('/teams/' . $teamId);
        }

        if (!$tmRepo->isUserInTeam($teamId, $userId)) {
            Flash::set('error', "Utilisateur non membre de l'equipe.");
            Response::redirect('/teams/' . $teamId);
        }

        $tmRepo->removeMember($teamId, $userId);

        Flash::set('success', 'Membre retire.');
        Response::redirect('/teams/' . $teamId);
    }

    /** @param array<string, string> $params */
    public function setCaptain(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $teamId = (int)($params['id'] ?? 0);
        $userId = (int)($params['userId'] ?? 0);
        if ($teamId <= 0 || $userId <= 0) {
            Response::notFound();
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null) {
            Response::notFound();
        }

        $tournamentId = (int)($team['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournament = $tRepo->findById($tournamentId);
        if ($tournament === null) {
            Response::notFound();
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $isCaptain = ($tmRepo->captainUserId($teamId) ?? 0) === $meId;
        $isAdmin = Auth::isAdmin();

        if (!$isAdmin && !$isCaptain) {
            Flash::set('error', "Acces refuse (capitaine requis).");
            Response::redirect('/teams/' . $teamId);
        }

        if (!$isAdmin && $this->isRosterLocked($tournament)) {
            Flash::set('error', 'Roster verrouille (inscriptions fermees ou bracket genere).');
            Response::redirect('/teams/' . $teamId);
        }

        if (!$tmRepo->isUserInTeam($teamId, $userId)) {
            Flash::set('error', "Utilisateur non membre de l'equipe.");
            Response::redirect('/teams/' . $teamId);
        }

        $tmRepo->setCaptain($teamId, $userId);

        Flash::set('success', 'Capitaine transfere.');
        Response::redirect('/teams/' . $teamId);
    }

    /** @param array<string, mixed> $tournament */
    private function isRosterLocked(array $tournament): bool
    {
        $tournamentId = (int)($tournament['id'] ?? 0);
        if ($tournamentId <= 0) {
            return true;
        }

        $mRepo = new MatchRepository();
        if ($mRepo->countForTournament($tournamentId) > 0) {
            return true; // bracket/schedule generated
        }

        $signupClosesAt = $tournament['signup_closes_at'] ?? null;
        if (is_string($signupClosesAt) && $signupClosesAt !== '') {
            $ts = strtotime($signupClosesAt);
            if ($ts !== false && $ts <= time()) {
                return true;
            }
        }

        return false;
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
