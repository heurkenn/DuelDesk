<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Database\Db;
use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TeamRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Services\BracketGenerator;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;
use Throwable;

final class AdminTournamentController
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        Auth::requireAdmin();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');

        $players = [];
        $teams = [];
        $teamMembers = [];

        if ($participantType === 'team') {
            $ttRepo = new TournamentTeamRepository();
            $teams = $ttRepo->listForTournament($tournamentId);

            $teamIds = [];
            foreach ($teams as $row) {
                $id = (int)($row['team_id'] ?? 0);
                if ($id > 0) {
                    $teamIds[] = $id;
                }
            }

            $tmRepo = new TeamMemberRepository();
            $teamMembers = $tmRepo->listMembersForTeams($teamIds);
        } else {
            $tpRepo = new TournamentPlayerRepository();
            $players = $tpRepo->listForTournament($tournamentId);
        }

        $mRepo = new MatchRepository();
        $matchCount = $mRepo->countForTournament($tournamentId);
        $format = (string)($t['format'] ?? 'single_elim');
        $participantCount = $participantType === 'team' ? count($teams) : count($players);
        $supportedFormat = in_array($format, ['single_elim', 'double_elim', 'round_robin'], true);
        $canGenerateBracket = $supportedFormat && ($matchCount === 0) && ($participantCount >= 2);

        $incompleteTeams = [];
        if ($participantType === 'team') {
            $teamSize = (int)($t['team_size'] ?? 0);
            if ($teamSize < 2) {
                $teamSize = 2;
            }

            foreach ($teams as $row) {
                $id = (int)($row['team_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $count = count($teamMembers[$id] ?? []);
                if ($count < $teamSize) {
                    $name = (string)($row['name'] ?? ('#' . $id));
                    $incompleteTeams[] = "{$name} ({$count}/{$teamSize})";
                    if (count($incompleteTeams) >= 5) {
                        break;
                    }
                }
            }

            if ($incompleteTeams !== []) {
                $canGenerateBracket = false;
            }
        }

        $matches = $participantType === 'team'
            ? $mRepo->listTeamForTournament($tournamentId)
            : $mRepo->listSoloForTournament($tournamentId);

        View::render('admin/tournament', [
            'title' => 'Gerer le tournoi | Admin | DuelDesk',
            'tournament' => $t,
            'players' => $players,
            'teams' => $teams,
            'teamMembers' => $teamMembers,
            'csrfToken' => Csrf::token(),
            'startsAtValue' => $this->toDatetimeLocal($t['starts_at'] ?? null),
            'maxEntrantsValue' => $t['max_entrants'] !== null ? (string)(int)$t['max_entrants'] : '',
            'signupClosesValue' => $this->toDatetimeLocal($t['signup_closes_at'] ?? null),
            'bestOfDefaultValue' => (string)(int)($t['best_of_default'] ?? 3),
            'matchCount' => $matchCount,
            'canGenerateBracket' => $canGenerateBracket,
            'incompleteTeams' => $incompleteTeams,
            'matches' => $matches,
        ]);
    }

    /** @param array<string, string> $params */
    public function updateSettings(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $status = (string)($_POST['status'] ?? '');
        $startsAtRaw = (string)($_POST['starts_at'] ?? '');
        $maxEntrantsRaw = trim((string)($_POST['max_entrants'] ?? ''));
        $signupClosesAtRaw = (string)($_POST['signup_closes_at'] ?? '');
        $bestOfRaw = trim((string)($_POST['best_of_default'] ?? '3'));

        $statuses = ['draft', 'published', 'running', 'completed'];
        if (!in_array($status, $statuses, true)) {
            Flash::set('error', 'Statut invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $startsAt = $this->normalizeStartsAt($startsAtRaw);
        if ($startsAtRaw !== '' && $startsAt === null) {
            Flash::set('error', 'Date de debut invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $maxEntrants = null;
        if ($maxEntrantsRaw !== '') {
            if (!ctype_digit($maxEntrantsRaw)) {
                Flash::set('error', 'Max entrants invalide.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
            $maxEntrants = (int)$maxEntrantsRaw;
            if ($maxEntrants <= 0) {
                $maxEntrants = null;
            } elseif ($maxEntrants < 2 || $maxEntrants > 1024) {
                Flash::set('error', 'Max entrants invalide (2 a 1024).');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        }

        $signupClosesAt = $this->normalizeStartsAt($signupClosesAtRaw);
        if ($signupClosesAtRaw !== '' && $signupClosesAt === null) {
            Flash::set('error', 'Date de fermeture invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $bestOfDefault = 3;
        if ($bestOfRaw === '' || !ctype_digit($bestOfRaw)) {
            Flash::set('error', 'Best-of invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }
        $bestOfDefault = (int)$bestOfRaw;
        if (!in_array($bestOfDefault, [1, 3, 5, 7, 9], true)) {
            Flash::set('error', 'Best-of invalide (1/3/5/7/9).');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $tRepo->updateSettings($tournamentId, $status, $startsAt, $maxEntrants, $signupClosesAt, $bestOfDefault);

        Flash::set('success', 'Parametres mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function setSeed(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $playerId = (int)($params['playerId'] ?? 0);
        if ($tournamentId <= 0 || $playerId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if (($t['participant_type'] ?? 'solo') !== 'solo') {
            Flash::set('error', 'Ce tournoi est en mode equipe.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo = new TournamentPlayerRepository();
        if (!$tpRepo->isPlayerInTournament($tournamentId, $playerId)) {
            Flash::set('error', 'Joueur non inscrit a ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $seedRaw = trim((string)($_POST['seed'] ?? ''));
        $seed = null;

        if ($seedRaw !== '') {
            if (!ctype_digit($seedRaw)) {
                Flash::set('error', 'Seed invalide.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }

            $seed = (int)$seedRaw;
            if ($seed <= 0) {
                $seed = null; // treat 0 as "clear seed"
            } elseif ($seed > 9999) {
                Flash::set('error', 'Seed trop grand (max 9999).');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        }

        if ($seed !== null && $tpRepo->seedTaken($tournamentId, $seed, $playerId)) {
            Flash::set('error', 'Seed deja utilise dans ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo->setSeed($tournamentId, $playerId, $seed);

        Flash::set('success', 'Seed mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function setCheckin(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $playerId = (int)($params['playerId'] ?? 0);
        if ($tournamentId <= 0 || $playerId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if (($t['participant_type'] ?? 'solo') !== 'solo') {
            Flash::set('error', 'Ce tournoi est en mode equipe.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $checkedInRaw = (string)($_POST['checked_in'] ?? '');
        if (!in_array($checkedInRaw, ['0', '1'], true)) {
            Flash::set('error', 'Valeur check-in invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo = new TournamentPlayerRepository();
        if (!$tpRepo->isPlayerInTournament($tournamentId, $playerId)) {
            Flash::set('error', 'Joueur non inscrit a ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo->setCheckedIn($tournamentId, $playerId, $checkedInRaw === '1');

        Flash::set('success', 'Check-in mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function removePlayer(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $playerId = (int)($params['playerId'] ?? 0);
        if ($tournamentId <= 0 || $playerId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if (($t['participant_type'] ?? 'solo') !== 'solo') {
            Flash::set('error', 'Ce tournoi est en mode equipe.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo = new TournamentPlayerRepository();
        if (!$tpRepo->isPlayerInTournament($tournamentId, $playerId)) {
            Flash::set('error', 'Joueur non inscrit a ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $tpRepo->remove($tournamentId, $playerId);

        Flash::set('success', 'Joueur retire.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function setTeamSeed(array $params = []): void
    {
        Auth::requireAdmin();

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
            Flash::set('error', 'Ce tournoi est en mode solo.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $ttRepo = new TournamentTeamRepository();
        if (!$ttRepo->isTeamInTournament($tournamentId, $teamId)) {
            Flash::set('error', 'Equipe non inscrite a ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $seedRaw = trim((string)($_POST['seed'] ?? ''));
        $seed = null;

        if ($seedRaw !== '') {
            if (!ctype_digit($seedRaw)) {
                Flash::set('error', 'Seed invalide.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }

            $seed = (int)$seedRaw;
            if ($seed <= 0) {
                $seed = null;
            } elseif ($seed > 9999) {
                Flash::set('error', 'Seed trop grand (max 9999).');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        }

        if ($seed !== null && $ttRepo->seedTaken($tournamentId, $seed, $teamId)) {
            Flash::set('error', 'Seed deja utilise dans ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $ttRepo->setSeed($tournamentId, $teamId, $seed);

        Flash::set('success', 'Seed mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function setTeamCheckin(array $params = []): void
    {
        Auth::requireAdmin();

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
            Flash::set('error', 'Ce tournoi est en mode solo.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $checkedInRaw = (string)($_POST['checked_in'] ?? '');
        if (!in_array($checkedInRaw, ['0', '1'], true)) {
            Flash::set('error', 'Valeur check-in invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $ttRepo = new TournamentTeamRepository();
        if (!$ttRepo->isTeamInTournament($tournamentId, $teamId)) {
            Flash::set('error', 'Equipe non inscrite a ce tournoi.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $ttRepo->setCheckedIn($tournamentId, $teamId, $checkedInRaw === '1');

        Flash::set('success', 'Check-in mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function removeTeam(array $params = []): void
    {
        Auth::requireAdmin();

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
            Flash::set('error', 'Ce tournoi est en mode solo.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $teamRepo = new TeamRepository();
        $team = $teamRepo->findById($teamId);
        if ($team === null || (int)($team['tournament_id'] ?? 0) !== $tournamentId) {
            Flash::set('error', 'Equipe introuvable.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $teamRepo->delete($teamId);

        Flash::set('success', 'Equipe retiree.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function generateBracket(array $params = []): void
    {
        Auth::requireAdmin();

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

        $format = (string)($t['format'] ?? 'single_elim');
        if (!in_array($format, ['single_elim', 'double_elim', 'round_robin'], true)) {
            Flash::set('error', 'Generation disponible uniquement pour single_elim / double_elim / round_robin.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $mRepo = new MatchRepository();
        if ($mRepo->countForTournament($tournamentId) > 0) {
            Flash::set('error', 'Bracket deja genere.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if ($participantType === 'team') {
            $ttRepo = new TournamentTeamRepository();
            $teams = $ttRepo->listForTournament($tournamentId);
            if (count($teams) < 2) {
                Flash::set('error', 'Il faut au moins 2 equipes.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
            if ($format === 'double_elim' && count($teams) < 4) {
                Flash::set('error', 'Il faut au moins 4 equipes pour double_elim.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }

            $teamSize = (int)($t['team_size'] ?? 0);
            if ($teamSize < 2) {
                $teamSize = 2;
            }

            $teamIds = [];
            foreach ($teams as $row) {
                $id = (int)($row['team_id'] ?? 0);
                if ($id > 0) {
                    $teamIds[] = $id;
                }
            }

            $tmRepo = new TeamMemberRepository();
            $members = $tmRepo->listMembersForTeams($teamIds);
            $incomplete = [];
            foreach ($teams as $row) {
                $id = (int)($row['team_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $count = count($members[$id] ?? []);
                if ($count < $teamSize) {
                    $name = (string)($row['name'] ?? ('#' . $id));
                    $incomplete[] = "{$name} ({$count}/{$teamSize})";
                    if (count($incomplete) >= 5) {
                        break;
                    }
                }
            }

            if ($incomplete !== []) {
                Flash::set('error', 'Equipe(s) incomplete(s): ' . implode(', ', $incomplete));
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        } else {
            $tpRepo = new TournamentPlayerRepository();
            if (count($tpRepo->listForTournament($tournamentId)) < 2) {
                Flash::set('error', 'Il faut au moins 2 joueurs.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
            if ($format === 'double_elim' && count($tpRepo->listForTournament($tournamentId)) < 4) {
                Flash::set('error', 'Il faut au moins 4 joueurs pour double_elim.');
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        }

        $gen = new BracketGenerator();
        $bestOf = (int)($t['best_of_default'] ?? 3);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }
        try {
            if ($format === 'double_elim') {
                $gen->generateDoubleElim($tournamentId, $participantType, $bestOf);
            } elseif ($format === 'round_robin') {
                $gen->generateRoundRobin($tournamentId, $participantType, $bestOf);
            } else {
                $gen->generateSingleElim($tournamentId, $participantType, $bestOf);
            }
        } catch (Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        Flash::set('success', 'Bracket genere.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function resetBracket(array $params = []): void
    {
        Auth::requireAdmin();

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

        $mRepo = new MatchRepository();
        $mRepo->deleteForTournament($tournamentId);

        Flash::set('success', 'Bracket reset (matchs supprimes).');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function scheduleMatch(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if ($match === null || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $raw = (string)($_POST['scheduled_at'] ?? '');
        $scheduledAt = $this->normalizeStartsAt($raw);
        if ($raw !== '' && $scheduledAt === null) {
            Flash::set('error', 'Horaire invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $st = (string)($match['status'] ?? 'pending');
        $newStatus = null;
        if ($st !== 'confirmed') {
            if ($scheduledAt !== null && $st === 'pending') {
                $newStatus = 'scheduled';
            } elseif ($scheduledAt === null && $st === 'scheduled') {
                $newStatus = 'pending';
            }
        }

        $mRepo->setSchedule($matchId, $scheduledAt, $newStatus);

        Flash::set('success', $scheduledAt !== null ? 'Match planifie.' : 'Horaire efface.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function setBestOf(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if ($match === null || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        if (($match['status'] ?? 'pending') === 'confirmed') {
            Flash::set('error', 'Match deja confirme (BO non modifiable).');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $raw = trim((string)($_POST['best_of'] ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            Flash::set('error', 'Best-of invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $bestOf = (int)$raw;
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            Flash::set('error', 'Best-of invalide (1/3/5/7/9).');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $mRepo->setBestOf($matchId, $bestOf);

        Flash::set('success', 'Best-of du match mis a jour.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function reportMatch(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if ($match === null || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        if (($match['status'] ?? 'pending') === 'confirmed') {
            Flash::set('error', 'Match deja confirme.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        $format = (string)($t['format'] ?? 'single_elim');
        $winnerSlot = (string)($_POST['winner_slot'] ?? '');

        $score1Raw = trim((string)($_POST['score1'] ?? ''));
        $score2Raw = trim((string)($_POST['score2'] ?? ''));

        if ($score1Raw === '' || $score2Raw === '' || !ctype_digit($score1Raw) || !ctype_digit($score2Raw)) {
            Flash::set('error', 'Scores invalides.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $score1 = (int)$score1Raw;
        $score2 = (int)$score2Raw;
        if ($score1 < 0 || $score2 < 0 || $score1 > 99 || $score2 > 99) {
            Flash::set('error', 'Scores invalides (0-99).');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        if (!in_array($winnerSlot, ['1', '2'], true)) {
            Flash::set('error', 'Winner invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        // Best-of validation (soft): if scores look like a series (<= BO), enforce "first to X wins".
        $bestOf = (int)($match['best_of'] ?? 0);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = (int)($t['best_of_default'] ?? 3);
        }
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }

        $winsToTake = intdiv($bestOf, 2) + 1;
        $looksLikeSeries = ($score1 <= $bestOf) && ($score2 <= $bestOf);

        // Always require winner to have a strictly higher score.
        if ($winnerSlot === '1' && $score1 <= $score2) {
            Flash::set('error', 'Score incoherent: winner=A mais scoreA <= scoreB.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }
        if ($winnerSlot === '2' && $score2 <= $score1) {
            Flash::set('error', 'Score incoherent: winner=B mais scoreB <= scoreA.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        // If it looks like a BO series, require majority wins.
        if ($looksLikeSeries) {
            if ($winnerSlot === '1' && $score1 !== $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
            if ($winnerSlot === '2' && $score2 !== $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
            if ($score1 >= $winsToTake && $score2 >= $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: les deux cotes ne peuvent pas atteindre {$winsToTake}.");
                Response::redirect('/admin/tournaments/' . $tournamentId);
            }
        }

        $bracket = (string)($match['bracket'] ?? 'winners');
        $round = (int)($match['round'] ?? 0);
        $roundPos = (int)($match['round_pos'] ?? 0);
        if ($round <= 0 || $roundPos <= 0) {
            Flash::set('error', 'Match invalide.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            if ($participantType === 'team') {
                $a = $match['team1_id'] !== null ? (int)$match['team1_id'] : null;
                $b = $match['team2_id'] !== null ? (int)$match['team2_id'] : null;
                if ($a === null || $b === null) {
                    throw new \RuntimeException('Match incomplet (TBD).');
                }

                $winnerTeamId = ($winnerSlot === '1') ? $a : $b;
                $loserTeamId = ($winnerSlot === '1') ? $b : $a;
                $mRepo->confirmTeamResult($matchId, $score1, $score2, $winnerTeamId);

                if ($format === 'double_elim') {
                    $this->advanceTeamDoubleElim($mRepo, $tournamentId, $bracket, $round, $roundPos, $winnerTeamId, $loserTeamId);
                } elseif ($format === 'single_elim') {
                    $this->advanceTeamWinner($mRepo, $tournamentId, $bracket, $round, $roundPos, $winnerTeamId);
                }
            } else {
                $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : null;
                $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : null;
                if ($a === null || $b === null) {
                    throw new \RuntimeException('Match incomplet (TBD).');
                }

                $winnerPlayerId = ($winnerSlot === '1') ? $a : $b;
                $loserPlayerId = ($winnerSlot === '1') ? $b : $a;
                $mRepo->confirmSoloResult($matchId, $score1, $score2, $winnerPlayerId);

                if ($format === 'double_elim') {
                    $this->advanceSoloDoubleElim($mRepo, $tournamentId, $bracket, $round, $roundPos, $winnerPlayerId, $loserPlayerId);
                } elseif ($format === 'single_elim') {
                    $this->advanceSoloWinner($mRepo, $tournamentId, $bracket, $round, $roundPos, $winnerPlayerId);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Flash::set('error', $e->getMessage());
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        Flash::set('success', 'Match confirme.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function rejectReport(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if ($match === null || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        if (($match['status'] ?? 'pending') !== 'reported') {
            Flash::set('error', 'Ce match n\'est pas en etat reported.');
            Response::redirect('/admin/tournaments/' . $tournamentId);
        }

        $mRepo->clearReport($matchId);

        Flash::set('success', 'Report rejete.');
        Response::redirect('/admin/tournaments/' . $tournamentId);
    }

    private function setSoloNextSlot(MatchRepository $mRepo, array $next, int $nextMatchId, int $slot, int $playerId): void
    {
        if (($next['status'] ?? 'pending') === 'confirmed') {
            throw new \RuntimeException('Conflit: le match suivant est deja confirme. Fais un reset bracket.');
        }

        $existing = $slot === 1 ? ($next['player1_id'] ?? null) : ($next['player2_id'] ?? null);
        if ($existing !== null && (int)$existing !== $playerId) {
            throw new \RuntimeException('Conflit: le match suivant est deja rempli. Fais un reset bracket.');
        }

        $mRepo->setSoloSlot($nextMatchId, $slot, $playerId);
    }

    private function setTeamNextSlot(MatchRepository $mRepo, array $next, int $nextMatchId, int $slot, int $teamId): void
    {
        if (($next['status'] ?? 'pending') === 'confirmed') {
            throw new \RuntimeException('Conflit: le match suivant est deja confirme. Fais un reset bracket.');
        }

        $existing = $slot === 1 ? ($next['team1_id'] ?? null) : ($next['team2_id'] ?? null);
        if ($existing !== null && (int)$existing !== $teamId) {
            throw new \RuntimeException('Conflit: le match suivant est deja rempli. Fais un reset bracket.');
        }

        $mRepo->setTeamSlot($nextMatchId, $slot, $teamId);
    }

    private function advanceSoloWinner(MatchRepository $mRepo, int $tournamentId, string $bracket, int $round, int $roundPos, int $winnerPlayerId): void
    {
        $next = $mRepo->findByTournamentKey($tournamentId, $bracket, $round + 1, (int)(($roundPos + 1) / 2));
        if ($next === null) {
            return;
        }

        $nextMatchId = (int)($next['id'] ?? 0);
        if ($nextMatchId <= 0) {
            return;
        }

        $slot = ($roundPos % 2 === 1) ? 1 : 2;
        $this->setSoloNextSlot($mRepo, $next, $nextMatchId, $slot, $winnerPlayerId);
    }

    private function advanceTeamWinner(MatchRepository $mRepo, int $tournamentId, string $bracket, int $round, int $roundPos, int $winnerTeamId): void
    {
        $next = $mRepo->findByTournamentKey($tournamentId, $bracket, $round + 1, (int)(($roundPos + 1) / 2));
        if ($next === null) {
            return;
        }

        $nextMatchId = (int)($next['id'] ?? 0);
        if ($nextMatchId <= 0) {
            return;
        }

        $slot = ($roundPos % 2 === 1) ? 1 : 2;
        $this->setTeamNextSlot($mRepo, $next, $nextMatchId, $slot, $winnerTeamId);
    }

    private function advanceSoloDoubleElim(
        MatchRepository $mRepo,
        int $tournamentId,
        string $bracket,
        int $round,
        int $roundPos,
        int $winnerPlayerId,
        int $loserPlayerId
    ): void
    {
        $wRounds = $mRepo->maxRoundForBracket($tournamentId, 'winners');
        if ($wRounds <= 0) {
            return;
        }

        $lRounds = (2 * $wRounds) - 2;
        if ($lRounds <= 0) {
            return;
        }

        $grand1 = $mRepo->findByTournamentKey($tournamentId, 'grand', 1, 1);
        $grand1Id = is_array($grand1) ? (int)($grand1['id'] ?? 0) : 0;
        $grand2 = $mRepo->findByTournamentKey($tournamentId, 'grand', 2, 1);
        $grand2Id = is_array($grand2) ? (int)($grand2['id'] ?? 0) : 0;

        if ($bracket === 'grand') {
            // GF2 is only played if the losers bracket champion wins GF1.
            if ($round !== 1 || $grand2Id <= 0 || !is_array($grand2)) {
                return;
            }

            $wf = $mRepo->findByTournamentKey($tournamentId, 'winners', $wRounds, 1);
            $wChamp = is_array($wf) ? (int)($wf['winner_id'] ?? 0) : 0;
            if ($wChamp <= 0) {
                return;
            }
            if ($wChamp !== $winnerPlayerId && $wChamp !== $loserPlayerId) {
                return;
            }

            $other = ($wChamp === $winnerPlayerId) ? $loserPlayerId : $winnerPlayerId;
            if ($other <= 0) {
                return;
            }

            if ($winnerPlayerId === $wChamp) {
                // Winners champ won GF1: no reset match.
                $mRepo->voidMatch($grand2Id);
                return;
            }

            // Losers champ won GF1: activate GF2 with the same participants.
            $mRepo->resetSoloForReplay($grand2Id, $wChamp, $other);
            return;
        }

        if ($bracket === 'winners') {
            // Winner -> next winners match (or grand final slot 1).
            if ($round >= $wRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setSoloNextSlot($mRepo, $grand1, $grand1Id, 1, $winnerPlayerId);
                }
            } else {
                $next = $mRepo->findByTournamentKey($tournamentId, 'winners', $round + 1, (int)(($roundPos + 1) / 2));
                if (is_array($next)) {
                    $nextId = (int)($next['id'] ?? 0);
                    if ($nextId > 0) {
                        $slot = ($roundPos % 2 === 1) ? 1 : 2;
                        $this->setSoloNextSlot($mRepo, $next, $nextId, $slot, $winnerPlayerId);
                    }
                }
            }

            // Loser -> losers bracket drop.
            $dropRound = $round === 1 ? 1 : ((2 * $round) - 2);
            if ($dropRound > 0 && $dropRound <= $lRounds) {
                $dropPos = $round === 1 ? (int)(($roundPos + 1) / 2) : $roundPos;
                $dropSlot = $round === 1 ? (($roundPos % 2 === 1) ? 1 : 2) : 2;

                $drop = $mRepo->findByTournamentKey($tournamentId, 'losers', $dropRound, $dropPos);
                if (is_array($drop)) {
                    $dropId = (int)($drop['id'] ?? 0);
                    if ($dropId > 0) {
                        $this->setSoloNextSlot($mRepo, $drop, $dropId, $dropSlot, $loserPlayerId);
                    }
                }
            }

            return;
        }

        if ($bracket === 'losers') {
            // Winner -> next losers match (or grand final slot 2).
            if ($round >= $lRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setSoloNextSlot($mRepo, $grand1, $grand1Id, 2, $winnerPlayerId);
                }
                return;
            }

            $nextRound = $round + 1;
            if ($round % 2 === 1) {
                // odd -> even: same pos, slot 1
                $nextPos = $roundPos;
                $nextSlot = 1;
            } else {
                // even -> odd: merge pairs
                $nextPos = (int)(($roundPos + 1) / 2);
                $nextSlot = ($roundPos % 2 === 1) ? 1 : 2;
            }

            $next = $mRepo->findByTournamentKey($tournamentId, 'losers', $nextRound, $nextPos);
            if (!is_array($next)) {
                return;
            }
            $nextId = (int)($next['id'] ?? 0);
            if ($nextId <= 0) {
                return;
            }

            $this->setSoloNextSlot($mRepo, $next, $nextId, $nextSlot, $winnerPlayerId);
        }
    }

    private function advanceTeamDoubleElim(
        MatchRepository $mRepo,
        int $tournamentId,
        string $bracket,
        int $round,
        int $roundPos,
        int $winnerTeamId,
        int $loserTeamId
    ): void
    {
        $wRounds = $mRepo->maxRoundForBracket($tournamentId, 'winners');
        if ($wRounds <= 0) {
            return;
        }

        $lRounds = (2 * $wRounds) - 2;
        if ($lRounds <= 0) {
            return;
        }

        $grand1 = $mRepo->findByTournamentKey($tournamentId, 'grand', 1, 1);
        $grand1Id = is_array($grand1) ? (int)($grand1['id'] ?? 0) : 0;
        $grand2 = $mRepo->findByTournamentKey($tournamentId, 'grand', 2, 1);
        $grand2Id = is_array($grand2) ? (int)($grand2['id'] ?? 0) : 0;

        if ($bracket === 'grand') {
            if ($round !== 1 || $grand2Id <= 0 || !is_array($grand2)) {
                return;
            }

            $wf = $mRepo->findByTournamentKey($tournamentId, 'winners', $wRounds, 1);
            $wChamp = is_array($wf) ? (int)($wf['winner_team_id'] ?? 0) : 0;
            if ($wChamp <= 0) {
                return;
            }
            if ($wChamp !== $winnerTeamId && $wChamp !== $loserTeamId) {
                return;
            }

            $other = ($wChamp === $winnerTeamId) ? $loserTeamId : $winnerTeamId;
            if ($other <= 0) {
                return;
            }

            if ($winnerTeamId === $wChamp) {
                $mRepo->voidMatch($grand2Id);
                return;
            }

            $mRepo->resetTeamForReplay($grand2Id, $wChamp, $other);
            return;
        }

        if ($bracket === 'winners') {
            if ($round >= $wRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setTeamNextSlot($mRepo, $grand1, $grand1Id, 1, $winnerTeamId);
                }
            } else {
                $next = $mRepo->findByTournamentKey($tournamentId, 'winners', $round + 1, (int)(($roundPos + 1) / 2));
                if (is_array($next)) {
                    $nextId = (int)($next['id'] ?? 0);
                    if ($nextId > 0) {
                        $slot = ($roundPos % 2 === 1) ? 1 : 2;
                        $this->setTeamNextSlot($mRepo, $next, $nextId, $slot, $winnerTeamId);
                    }
                }
            }

            $dropRound = $round === 1 ? 1 : ((2 * $round) - 2);
            if ($dropRound > 0 && $dropRound <= $lRounds) {
                $dropPos = $round === 1 ? (int)(($roundPos + 1) / 2) : $roundPos;
                $dropSlot = $round === 1 ? (($roundPos % 2 === 1) ? 1 : 2) : 2;

                $drop = $mRepo->findByTournamentKey($tournamentId, 'losers', $dropRound, $dropPos);
                if (is_array($drop)) {
                    $dropId = (int)($drop['id'] ?? 0);
                    if ($dropId > 0) {
                        $this->setTeamNextSlot($mRepo, $drop, $dropId, $dropSlot, $loserTeamId);
                    }
                }
            }

            return;
        }

        if ($bracket === 'losers') {
            if ($round >= $lRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setTeamNextSlot($mRepo, $grand1, $grand1Id, 2, $winnerTeamId);
                }
                return;
            }

            $nextRound = $round + 1;
            if ($round % 2 === 1) {
                $nextPos = $roundPos;
                $nextSlot = 1;
            } else {
                $nextPos = (int)(($roundPos + 1) / 2);
                $nextSlot = ($roundPos % 2 === 1) ? 1 : 2;
            }

            $next = $mRepo->findByTournamentKey($tournamentId, 'losers', $nextRound, $nextPos);
            if (!is_array($next)) {
                return;
            }
            $nextId = (int)($next['id'] ?? 0);
            if ($nextId <= 0) {
                return;
            }

            $this->setTeamNextSlot($mRepo, $next, $nextId, $nextSlot, $winnerTeamId);
        }
    }

    private function normalizeStartsAt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Expect HTML datetime-local: YYYY-MM-DDTHH:MM
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

        // DB: YYYY-MM-DD HH:MM:SS -> input: YYYY-MM-DDTHH:MM
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $dbValue)) {
            return '';
        }

        return substr($dbValue, 0, 16) !== false ? str_replace(' ', 'T', substr($dbValue, 0, 16)) : '';
    }
}
