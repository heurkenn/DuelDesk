<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class TournamentController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $repo = new TournamentRepository();

        View::render('tournaments/index', [
            'title' => 'Tournois | DuelDesk',
            'tournaments' => $repo->all(),
        ]);
    }

    /** @param array<string, string> $params */
    public function new(array $params = []): void
    {
        Auth::requireAdmin();

        $gRepo = new GameRepository();
        $games = $gRepo->all();
        $defaultGameId = $games !== [] ? (string)$games[0]['id'] : '';

        View::render('tournaments/new', [
            'title' => 'Nouveau tournoi | DuelDesk',
            'games' => $games,
            'old' => [
                'name' => '',
                'game_id' => $defaultGameId,
                'format' => 'single_elim',
                'participant_type' => 'solo',
                'team_size' => '2',
                'max_entrants' => '',
                'signup_closes_at' => '',
                'best_of_default' => '3',
                'status' => 'draft',
                'starts_at' => '',
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

        $name = trim((string)($_POST['name'] ?? ''));
        $gameId = (int)($_POST['game_id'] ?? 0);
        $format = (string)($_POST['format'] ?? 'single_elim');
        $participantType = (string)($_POST['participant_type'] ?? 'solo');
        $teamSizeRaw = trim((string)($_POST['team_size'] ?? ''));
        $maxEntrantsRaw = trim((string)($_POST['max_entrants'] ?? ''));
        $signupClosesAtRaw = (string)($_POST['signup_closes_at'] ?? '');
        $bestOfRaw = trim((string)($_POST['best_of_default'] ?? '3'));
        $status = (string)($_POST['status'] ?? 'draft');
        $startsAtRaw = (string)($_POST['starts_at'] ?? '');

        $old = [
            'name' => $name,
            'game_id' => (string)$gameId,
            'format' => $format,
            'participant_type' => $participantType,
            'team_size' => $teamSizeRaw,
            'max_entrants' => $maxEntrantsRaw,
            'signup_closes_at' => $signupClosesAtRaw,
            'best_of_default' => $bestOfRaw,
            'status' => $status,
            'starts_at' => $startsAtRaw,
        ];

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $errors = [];
        if ($name === '' || $this->strlenSafe($name) > 120) {
            $errors['name'] = 'Nom requis (max 120).';
        }

        $game = null;
        if ($gameId <= 0) {
            $errors['game_id'] = 'Jeu requis.';
        } else {
            $game = $gRepo->findById($gameId);
            if ($game === null) {
                $errors['game_id'] = 'Jeu invalide.';
            }
        }

        $formats = ['single_elim', 'double_elim', 'round_robin'];
        if (!in_array($format, $formats, true)) {
            $errors['format'] = 'Format invalide.';
        }

        $participantTypes = ['solo', 'team'];
        if (!in_array($participantType, $participantTypes, true)) {
            $errors['participant_type'] = 'Type de participants invalide.';
        }

        $teamSize = null;
        if ($participantType === 'team') {
            if ($teamSizeRaw === '' || !ctype_digit($teamSizeRaw)) {
                $errors['team_size'] = "Taille d'equipe requise.";
            } else {
                $teamSize = (int)$teamSizeRaw;
                if ($teamSize < 2 || $teamSize > 16) {
                    $errors['team_size'] = "Taille d'equipe invalide (2 a 16).";
                }
            }
        }

        $maxEntrants = null;
        if ($maxEntrantsRaw !== '') {
            if (!ctype_digit($maxEntrantsRaw)) {
                $errors['max_entrants'] = 'Max entrants invalide.';
            } else {
                $maxEntrants = (int)$maxEntrantsRaw;
                if ($maxEntrants <= 0) {
                    $maxEntrants = null;
                } elseif ($maxEntrants < 2 || $maxEntrants > 1024) {
                    $errors['max_entrants'] = 'Max entrants invalide (2 a 1024).';
                }
            }
        }

        $statuses = ['draft', 'published', 'running', 'completed'];
        if (!in_array($status, $statuses, true)) {
            $errors['status'] = 'Statut invalide.';
        }

        $startsAt = $this->normalizeStartsAt($startsAtRaw);
        if ($startsAtRaw !== '' && $startsAt === null) {
            $errors['starts_at'] = 'Date de debut invalide.';
        }

        $signupClosesAt = $this->normalizeStartsAt($signupClosesAtRaw);
        if ($signupClosesAtRaw !== '' && $signupClosesAt === null) {
            $errors['signup_closes_at'] = 'Date de fermeture invalide.';
        }

        $bestOfDefault = 3;
        if ($bestOfRaw === '' || !ctype_digit($bestOfRaw)) {
            $errors['best_of_default'] = 'Best-of invalide.';
        } else {
            $bestOfDefault = (int)$bestOfRaw;
            $allowed = [1, 3, 5, 7, 9];
            if (!in_array($bestOfDefault, $allowed, true)) {
                $errors['best_of_default'] = 'Best-of invalide (1/3/5/7/9).';
            }
        }

        if ($errors !== []) {
            View::render('tournaments/new', [
                'title' => 'Nouveau tournoi | DuelDesk',
                'games' => $games,
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        $gameName = (string)$game['name'];

        $repo = new TournamentRepository();
        $id = $repo->create(Auth::id(), $gameId, $gameName, $name, $format, $participantType, $teamSize, $status, $startsAt, $maxEntrants, $signupClosesAt, $bestOfDefault);

        Flash::set('success', 'Tournoi cree.');
        Response::redirect('/tournaments/' . $id);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new TournamentRepository();
        $tournament = $repo->findById($id);
        if ($tournament === null) {
            Response::notFound();
        }

        $this->renderShow($tournament);
    }

    /** @param array<string, string> $params */
    public function showBySlug(array $params = []): void
    {
        $slug = (string)($params['slug'] ?? '');
        if ($slug === '') {
            Response::notFound();
        }

        $repo = new TournamentRepository();
        $tournament = $repo->findBySlug($slug);
        if ($tournament === null) {
            Response::notFound();
        }

        $this->renderShow($tournament, true);
    }

    /** @param array<string, string> $params */
    public function match(array $params = []): void
    {
        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $tournament = $tRepo->findById($tournamentId);
        if ($tournament === null) {
            Response::notFound();
        }

        $participantType = (string)($tournament['participant_type'] ?? 'solo');

        $mRepo = new MatchRepository();
        $match = $participantType === 'team'
            ? $mRepo->findTeamDetailed($matchId)
            : $mRepo->findSoloDetailed($matchId);

        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $canReport = false;
        if (Auth::check()) {
            if (Auth::isAdmin()) {
                $canReport = true;
            } else {
                $meId = Auth::id();
                if ($meId !== null) {
                    if ($participantType === 'team') {
                        $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
                        $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
                        if ($t1 > 0 || $t2 > 0) {
                            $tmRepo = new TeamMemberRepository();
                            $canReport = ($t1 > 0 && $tmRepo->isCaptain($t1, $meId)) || ($t2 > 0 && $tmRepo->isCaptain($t2, $meId));
                        }
                    } else {
                        $pRepo = new PlayerRepository();
                        $p = $pRepo->findByUserId($meId);
                        if ($p !== null) {
                            $pid = (int)($p['id'] ?? 0);
                            $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
                            $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
                            $canReport = $pid > 0 && ($pid === $a || $pid === $b);
                        }
                    }

                    $st = (string)($match['status'] ?? 'pending');
                    $reportedBy = $match['reported_by_user_id'] ?? null;
                    $reportedById = (is_int($reportedBy) || is_string($reportedBy)) ? (int)$reportedBy : 0;
                    if ($st === 'reported' && $reportedById > 0 && $reportedById !== $meId) {
                        $canReport = false;
                    }
                }
            }
        }

        View::render('tournaments/match', [
            'title' => 'Match | ' . ((string)($tournament['name'] ?? 'Tournoi')) . ' | DuelDesk',
            'tournament' => $tournament,
            'match' => $match,
            'participantType' => $participantType,
            'csrfToken' => Csrf::token(),
            'canReport' => $canReport,
        ]);
    }

    private function normalizeStartsAt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Expect HTML datetime-local: YYYY-MM-DDTHH:MM
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        return $value . ':00';
    }

    /** @param array<string, mixed> $tournament */
    private function renderShow(array $tournament, bool $isPublicView = false): void
    {
        $id = (int)($tournament['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $participantType = (string)($tournament['participant_type'] ?? 'solo');

        $players = [];
        $teams = [];
        $teamMembers = [];
        $mePlayerId = null;
        $meTeam = null;
        $isSignedUp = false;

        if ($participantType === 'team') {
            $ttRepo = new TournamentTeamRepository();
            $teams = $ttRepo->listForTournament($id);

            $teamIds = [];
            foreach ($teams as $t) {
                $tid = (int)($t['team_id'] ?? 0);
                if ($tid > 0) {
                    $teamIds[] = $tid;
                }
            }

            $tmRepo = new TeamMemberRepository();
            $teamMembers = $tmRepo->listMembersForTeams($teamIds);

            if (Auth::check()) {
                $meId = Auth::id();
                if ($meId !== null) {
                    $meTeam = $tmRepo->findTeamForUserInTournament($id, $meId);
                    $isSignedUp = $meTeam !== null;
                }
            }
        } else {
            $tpRepo = new TournamentPlayerRepository();
            $players = $tpRepo->listForTournament($id);

            if (Auth::check()) {
                $meId = Auth::id();
                if ($meId !== null) {
                    $pRepo = new PlayerRepository();
                    $p = $pRepo->findByUserId($meId);
                    if ($p !== null) {
                        $mePlayerId = (int)$p['id'];
                        $isSignedUp = $tpRepo->isPlayerInTournament($id, $mePlayerId);
                    }
                }
            }
        }

        $mRepo = new MatchRepository();
        $matches = $participantType === 'team'
            ? $mRepo->listTeamForTournament($id)
            : $mRepo->listSoloForTournament($id);

        View::render('tournaments/show', [
            'title' => ($tournament['name'] ?? 'Tournoi') . ' | DuelDesk',
            'tournament' => $tournament,
            'players' => $players,
            'teams' => $teams,
            'teamMembers' => $teamMembers,
            'isSignedUp' => $isSignedUp,
            'mePlayerId' => $mePlayerId,
            'meTeam' => $meTeam,
            'matches' => $matches,
            'isPublicView' => $isPublicView,
            'csrfToken' => Csrf::token(),
        ]);
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
