<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PickBanRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Services\PickBanEngine;
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
        $query = trim((string)($_GET['q'] ?? ''));
        $pageRaw = trim((string)($_GET['page'] ?? '1'));
        $page = (ctype_digit($pageRaw) && (int)$pageRaw > 0) ? (int)$pageRaw : 1;
        $perPage = 20;

        $total = $repo->countSearchPublic($query);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        View::render('tournaments/index', [
            'title' => 'Tournois | DuelDesk',
            'tournaments' => $repo->searchPagedPublic($query, $page, $perPage),
            'query' => $query,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    /** @param array<string, string> $params */
    public function new(array $params = []): void
    {
        Auth::requireAdmin();

        $gRepo = new GameRepository();
        $games = $gRepo->all();
        $defaultGameId = $games !== [] ? (string)$games[0]['id'] : '';

        $lanRepo = new LanEventRepository();
        $lanEvents = $lanRepo->listForSelect();
        $defaultLanId = '';
        $lanIdRaw = trim((string)($_GET['lan_event_id'] ?? ''));
        if ($lanIdRaw !== '' && ctype_digit($lanIdRaw) && (int)$lanIdRaw > 0) {
            $lanId = (int)$lanIdRaw;
            $exists = $lanRepo->findById($lanId);
            if ($exists !== null) {
                $defaultLanId = (string)$lanId;
            }
        }

        View::render('tournaments/new', [
            'title' => 'Nouveau tournoi | DuelDesk',
            'games' => $games,
            'lanEvents' => $lanEvents,
            'old' => [
                'name' => '',
                'game_id' => $defaultGameId,
                'format' => 'single_elim',
                'participant_type' => 'solo',
                'team_size' => '2',
                'team_match_mode' => 'standard',
                'max_entrants' => '',
                'signup_closes_at' => '',
                'best_of_default' => '3',
                'best_of_final' => '',
                'pickban_start_mode' => 'coin_toss',
                'lan_event_id' => $defaultLanId,
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
        $teamMatchMode = trim((string)($_POST['team_match_mode'] ?? 'standard'));
        $maxEntrantsRaw = trim((string)($_POST['max_entrants'] ?? ''));
        $signupClosesAtRaw = (string)($_POST['signup_closes_at'] ?? '');
        $bestOfRaw = trim((string)($_POST['best_of_default'] ?? '3'));
        $bestOfFinalRaw = trim((string)($_POST['best_of_final'] ?? ''));
        $pickbanStartMode = trim((string)($_POST['pickban_start_mode'] ?? 'coin_toss'));
        $lanEventIdRaw = trim((string)($_POST['lan_event_id'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $startsAtRaw = (string)($_POST['starts_at'] ?? '');

        $old = [
            'name' => $name,
            'game_id' => (string)$gameId,
            'format' => $format,
            'participant_type' => $participantType,
            'team_size' => $teamSizeRaw,
            'team_match_mode' => $teamMatchMode,
            'max_entrants' => $maxEntrantsRaw,
            'signup_closes_at' => $signupClosesAtRaw,
            'best_of_default' => $bestOfRaw,
            'best_of_final' => $bestOfFinalRaw,
            'pickban_start_mode' => $pickbanStartMode,
            'lan_event_id' => $lanEventIdRaw,
            'status' => $status,
            'starts_at' => $startsAtRaw,
        ];

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $lanRepo = new LanEventRepository();
        $lanEvents = $lanRepo->listForSelect();

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

        $teamMatchMode = $participantType === 'team' ? $teamMatchMode : 'standard';
        $teamMatchModes = ['standard', 'lineup_duels', 'multi_round'];
        if ($participantType === 'team' && !in_array($teamMatchMode, $teamMatchModes, true)) {
            $errors['team_match_mode'] = "Mode d'equipe invalide.";
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

        $bestOfFinal = null;
        if ($bestOfFinalRaw !== '') {
            if (!ctype_digit($bestOfFinalRaw)) {
                $errors['best_of_final'] = 'Best-of finale invalide.';
            } else {
                $bestOfFinal = (int)$bestOfFinalRaw;
                $allowed = [1, 3, 5, 7, 9];
                if (!in_array($bestOfFinal, $allowed, true)) {
                    $errors['best_of_final'] = 'Best-of finale invalide (1/3/5/7/9).';
                }
            }
        }

        $pickbanModes = ['coin_toss', 'higher_seed'];
        if (!in_array($pickbanStartMode, $pickbanModes, true)) {
            $errors['pickban_start_mode'] = 'Mode pick/ban invalide.';
        }

        $lanEventId = null;
        if ($lanEventIdRaw !== '') {
            if (!ctype_digit($lanEventIdRaw)) {
                $errors['lan_event_id'] = 'LAN invalide.';
            } else {
                $lanEventId = (int)$lanEventIdRaw;
                if ($lanEventId <= 0) {
                    $errors['lan_event_id'] = 'LAN introuvable.';
                } else {
                    $lan = $lanRepo->findById($lanEventId);
                    if ($lan === null) {
                        $errors['lan_event_id'] = 'LAN introuvable.';
                    } else {
                        $lanType = (string)($lan['participant_type'] ?? 'solo');
                        if (!in_array($lanType, ['solo', 'team'], true)) {
                            $lanType = 'solo';
                        }
                        if ($lanType !== $participantType) {
                            $errors['lan_event_id'] = "Type incompatible: LAN={$lanType}, tournoi={$participantType}.";
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            View::render('tournaments/new', [
                'title' => 'Nouveau tournoi | DuelDesk',
                'games' => $games,
                'lanEvents' => $lanEvents,
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        $gameName = (string)$game['name'];

        $repo = new TournamentRepository();
        $id = $repo->create(Auth::id(), $gameId, $gameName, $name, $format, $participantType, $teamSize, $teamMatchMode, $status, $startsAt, $maxEntrants, $signupClosesAt, $bestOfDefault, $bestOfFinal, $pickbanStartMode, $lanEventId);

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

        // LAN tournaments should be accessed from their LAN context (public).
        $lanSlug = is_string($tournament['lan_event_slug'] ?? null) ? trim((string)$tournament['lan_event_slug']) : '';
        $tSlug = is_string($tournament['slug'] ?? null) ? trim((string)$tournament['slug']) : '';
        if ($lanSlug !== '' && $tSlug !== '' && !Auth::isAdmin()) {
            Response::redirect('/lan/' . $lanSlug . '/t/' . $tSlug);
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

        // LAN tournaments have a canonical public URL under the LAN.
        $lanSlug = is_string($tournament['lan_event_slug'] ?? null) ? trim((string)$tournament['lan_event_slug']) : '';
        if ($lanSlug !== '') {
            Response::redirect('/lan/' . $lanSlug . '/t/' . $slug);
        }

        $this->renderShow($tournament, true);
    }

    /** @param array<string, string> $params */
    public function showInLan(array $params = []): void
    {
        $lanSlug = (string)($params['lanSlug'] ?? '');
        $slug = (string)($params['slug'] ?? '');
        if ($lanSlug === '' || $slug === '') {
            Response::notFound();
        }

        $lanRepo = new LanEventRepository();
        $lan = $lanRepo->findBySlug($lanSlug);
        if ($lan === null) {
            Response::notFound();
        }

        $repo = new TournamentRepository();
        $tournament = $repo->findBySlug($slug);
        if ($tournament === null) {
            Response::notFound();
        }

        $expectedLanId = (int)($lan['id'] ?? 0);
        $actualLanId = $tournament['lan_event_id'] ?? null;
        $actualLanId = (is_int($actualLanId) || is_string($actualLanId)) ? (int)$actualLanId : 0;
        if ($expectedLanId <= 0 || $actualLanId !== $expectedLanId) {
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
        $teamMatchMode = (string)($tournament['team_match_mode'] ?? 'standard');
        if (!in_array($teamMatchMode, ['standard', 'lineup_duels', 'multi_round'], true)) {
            $teamMatchMode = 'standard';
        }
        $disableGenericReport = false;
        $teamRoster1 = [];
        $teamRoster2 = [];
        $teamLineup1 = [];
        $teamLineup2 = [];
        $teamDuels = [];
        $teamCanSetLineup1 = false;
        $teamCanSetLineup2 = false;
        $teamCanConfirmDuel = false;
        $teamDuelWins1 = 0;
        $teamDuelWins2 = 0;
        $teamNeedsCaptainTiebreak = false;
        $multiRounds = [];
        $multiTotal1 = 0;
        $multiTotal2 = 0;
        $multiCanAddRound = false;
        $multiCanFinalize = false;
        $pickbanMySlot = null;
        $pickbanRequired = false;
        $pickbanLocked = false;
        $pickbanBlockingReport = false;
        $pickbanState = null;
        $pickbanActions = [];
        $pickbanSides = [];
        $pickbanComputed = null;
        $pickbanIsMyTurn = false;
        $pickbanStartMode = (string)($tournament['pickban_start_mode'] ?? 'coin_toss');
        if (!in_array($pickbanStartMode, ['coin_toss', 'higher_seed'], true)) {
            $pickbanStartMode = 'coin_toss';
        }
        $pickbanSeedA = null;
        $pickbanSeedB = null;
        $pickbanHigherSeedSlot = null;

        $matchComplete = $participantType === 'team'
            ? ($match['team1_id'] !== null && $match['team2_id'] !== null)
            : ($match['player1_id'] !== null && $match['player2_id'] !== null);

        $status = (string)($match['status'] ?? 'pending');

        // Team "lineup duels" (crew battle) mode: captains set an order, then confirm each duel.
        if ($participantType === 'team' && $teamMatchMode === 'lineup_duels' && $matchComplete && !in_array($status, ['confirmed', 'void'], true)) {
            $disableGenericReport = true;
            $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($t1 > 0 && $t2 > 0) {
                $tmRepo = new TeamMemberRepository();
                $teamRoster1 = $tmRepo->listMembers($t1);
                $teamRoster2 = $tmRepo->listMembers($t2);

                $lineupRepo = new \DuelDesk\Repositories\MatchTeamLineupRepository();
                $teamLineup1 = $lineupRepo->listLineup($matchId, 1);
                $teamLineup2 = $lineupRepo->listLineup($matchId, 2);

                // Auto-create regular duels once both lineups exist.
                try {
                    $svc = new \DuelDesk\Services\TeamMatchService();
                    $svc->ensureRegularDuels($tournament, $match);
                } catch (\Throwable) {
                    // Best-effort; UI still works if duels aren't created yet.
                }

                $duelRepo = new \DuelDesk\Repositories\MatchTeamDuelRepository();
                $teamDuels = $duelRepo->listDuels($matchId);
                foreach ($teamDuels as $d) {
                    if (!is_array($d)) continue;
                    if ((string)($d['status'] ?? 'pending') !== 'confirmed') continue;
                    $w = $d['winner_slot'] ?? null;
                    $w = (is_int($w) || is_string($w)) ? (int)$w : 0;
                    if ($w === 1) $teamDuelWins1++;
                    if ($w === 2) $teamDuelWins2++;
                }

                // If regular duels done and tied, tiebreaker duel will be created (and must be confirmed).
                $teamSize = (int)($tournament['team_size'] ?? 0);
                if ($teamSize <= 0) $teamSize = 2;
                $confirmedRegular = 0;
                foreach ($teamDuels as $d) {
                    if (!is_array($d)) continue;
                    if ((string)($d['kind'] ?? '') !== 'regular') continue;
                    if ((string)($d['status'] ?? 'pending') === 'confirmed') $confirmedRegular++;
                }
                if ($confirmedRegular >= $teamSize && $teamDuelWins1 === $teamDuelWins2) {
                    $teamNeedsCaptainTiebreak = true;
                }

                if (Auth::check()) {
                    $meId = Auth::id();
                    if ($meId !== null) {
                        $teamCanSetLineup1 = Auth::isAdmin() || $tmRepo->isCaptain($t1, $meId);
                        $teamCanSetLineup2 = Auth::isAdmin() || $tmRepo->isCaptain($t2, $meId);
                        $teamCanConfirmDuel = Auth::isAdmin()
                            || $tmRepo->isCaptain($t1, $meId)
                            || $tmRepo->isCaptain($t2, $meId);
                    }
                }

                // In this mode, don't show the generic "report match score" form.
                $canReport = false;
            }
        }

        // Team "multi-round" points mode (Fall Guys-style).
        if ($participantType === 'team' && $teamMatchMode === 'multi_round' && $matchComplete && !in_array($status, ['confirmed', 'void'], true)) {
            $disableGenericReport = true;
            $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($t1 > 0 && $t2 > 0) {
                $rRepo = new \DuelDesk\Repositories\MatchRoundRepository();
                $multiRounds = $rRepo->listForMatch($matchId);
                foreach ($multiRounds as $r) {
                    if (!is_array($r)) continue;
                    $multiTotal1 += (int)($r['points1'] ?? 0);
                    $multiTotal2 += (int)($r['points2'] ?? 0);
                }

                if (Auth::check()) {
                    $meId = Auth::id();
                    if ($meId !== null) {
                        $tmRepo = new TeamMemberRepository();
                        $multiCanAddRound = Auth::isAdmin() || $tmRepo->isCaptain($t1, $meId) || $tmRepo->isCaptain($t2, $meId);
                        $multiCanFinalize = $multiCanAddRound;
                    }
                }

                $canReport = false;
            }
        }

        // Determine if pick/ban is enabled for this match (depends on tournament ruleset + BO).
        $rulesetJson = is_string($tournament['ruleset_json'] ?? null) ? trim((string)$tournament['ruleset_json']) : '';
        $pickbanConfig = null;
        if ($rulesetJson !== '' && $matchComplete && !in_array($status, ['confirmed', 'void'], true)) {
            $bestOf = (int)($match['best_of'] ?? 0);
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = (int)($tournament['best_of_default'] ?? 3);
            }
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = 3;
            }

            $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
            if (is_array($parsed['ruleset'] ?? null)) {
                $pickbanConfig = PickBanEngine::buildMatchConfigSnapshot($parsed['ruleset'], $bestOf);
            }
        }

        // Load pick/ban state if any (uses a snapshot stored per match).
        if ($matchComplete && !in_array($status, ['confirmed', 'void'], true)) {
            $pbRepo = new PickBanRepository();
            $pickbanState = $pbRepo->findState($matchId);
            if (is_array($pickbanState)) {
                $pickbanRequired = true;
                $pickbanLocked = ((string)($pickbanState['status'] ?? 'running')) === 'locked';
                $pickbanActions = $pbRepo->listActions($matchId);
                $pickbanSides = $pbRepo->listSides($matchId);

                $cfgFromState = PickBanEngine::decodeJson((string)($pickbanState['config_json'] ?? ''));
                if (is_array($cfgFromState)) {
                    $pickbanConfig = $cfgFromState;
                }

                $firstTurnSlot = (int)($pickbanState['first_turn_slot'] ?? 0);
                if (is_array($pickbanConfig)) {
                    $pickbanComputed = PickBanEngine::compute($pickbanConfig, $firstTurnSlot, $pickbanActions, $pickbanSides);
                }
            } elseif ($pickbanConfig !== null) {
                $pickbanRequired = true;
            }
        }

        // Higher seed (optional): can decide who is Team A/B instead of coin toss.
        $pbCoinCallSlot = is_array($pickbanState) ? (int)($pickbanState['coin_call_slot'] ?? 0) : 0;
        $startedAsHigherSeed = is_array($pickbanState) && ($pbCoinCallSlot !== 1 && $pbCoinCallSlot !== 2);
        if (($pickbanStartMode === 'higher_seed' || $startedAsHigherSeed) && $pickbanRequired && $matchComplete) {
            if ($participantType === 'team') {
                $aId = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
                $bId = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
                if ($aId > 0 && $bId > 0) {
                    $ttRepo = new TournamentTeamRepository();
                    $pickbanSeedA = $ttRepo->findSeed($tournamentId, $aId);
                    $pickbanSeedB = $ttRepo->findSeed($tournamentId, $bId);
                }
            } else {
                $aId = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
                $bId = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
                if ($aId > 0 && $bId > 0) {
                    $tpRepo = new TournamentPlayerRepository();
                    $pickbanSeedA = $tpRepo->findSeed($tournamentId, $aId);
                    $pickbanSeedB = $tpRepo->findSeed($tournamentId, $bId);
                }
            }

            if ($pickbanSeedA !== null && $pickbanSeedB !== null) {
                $pickbanHigherSeedSlot = $pickbanSeedA <= $pickbanSeedB ? 1 : 2;
            } elseif ($pickbanSeedA !== null) {
                $pickbanHigherSeedSlot = 1;
            } elseif ($pickbanSeedB !== null) {
                $pickbanHigherSeedSlot = 2;
            } else {
                $pickbanHigherSeedSlot = 1;
            }
        }

        // Resolve my pick/ban slot (A/B) for UI + turn checking.
        // Note: admins can also be participants/captains; don't block slot resolution based on role.
        if ($pickbanRequired && Auth::check() && $matchComplete) {
            $meId = Auth::id();
            if ($meId !== null) {
                if ($participantType === 'team') {
                    $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
                    $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
                    if ($t1 > 0 || $t2 > 0) {
                        $tmRepo = new TeamMemberRepository();
                        if ($t1 > 0 && $tmRepo->isCaptain($t1, $meId)) {
                            $pickbanMySlot = 1;
                        } elseif ($t2 > 0 && $tmRepo->isCaptain($t2, $meId)) {
                            $pickbanMySlot = 2;
                        }
                    }
                } else {
                    $pRepo = new PlayerRepository();
                    $p = $pRepo->findByUserId($meId);
                    if ($p !== null) {
                        $pid = (int)($p['id'] ?? 0);
                        $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
                        $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
                        if ($pid > 0 && $pid === $a) {
                            $pickbanMySlot = 1;
                        } elseif ($pid > 0 && $pid === $b) {
                            $pickbanMySlot = 2;
                        }
                    }
                }
            }
        }

        if ($pickbanRequired && !$pickbanLocked && !Auth::isAdmin()) {
            $pickbanBlockingReport = true;
        }

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
                    if ($st === 'disputed') {
                        $a = $match['reported_by_user_id'] ?? null;
                        $b = $match['counter_reported_by_user_id'] ?? null;
                        $a = (is_int($a) || is_string($a)) ? (int)$a : 0;
                        $b = (is_int($b) || is_string($b)) ? (int)$b : 0;
                        if ($a > 0 && $b > 0 && $meId !== $a && $meId !== $b) {
                            $canReport = false;
                        }
                    }
                }
            }
        }

        if ($pickbanBlockingReport) {
            $canReport = false;
        }
        if ($disableGenericReport) {
            $canReport = false;
        }

        if (is_array($pickbanComputed) && ($pickbanComputed['ok'] ?? false) && $pickbanMySlot !== null) {
            $nextStep = (string)($pickbanComputed['next_step'] ?? '');
            $nextSlot = (int)($pickbanComputed['next_slot'] ?? 0);
            $pickbanIsMyTurn = in_array($nextStep, ['ban', 'pick', 'side'], true) && $nextSlot === $pickbanMySlot;
        }

        View::render('tournaments/match', [
            'title' => 'Match | ' . ((string)($tournament['name'] ?? 'Tournoi')) . ' | DuelDesk',
            'tournament' => $tournament,
            'match' => $match,
            'participantType' => $participantType,
            'csrfToken' => Csrf::token(),
            'canReport' => $canReport,
            'teamMatchMode' => $teamMatchMode,
            'teamRoster1' => $teamRoster1,
            'teamRoster2' => $teamRoster2,
            'teamLineup1' => $teamLineup1,
            'teamLineup2' => $teamLineup2,
            'teamDuels' => $teamDuels,
            'teamCanSetLineup1' => $teamCanSetLineup1,
            'teamCanSetLineup2' => $teamCanSetLineup2,
            'teamCanConfirmDuel' => $teamCanConfirmDuel,
            'teamDuelWins1' => $teamDuelWins1,
            'teamDuelWins2' => $teamDuelWins2,
            'teamNeedsCaptainTiebreak' => $teamNeedsCaptainTiebreak,
            'multiRounds' => $multiRounds,
            'multiTotal1' => $multiTotal1,
            'multiTotal2' => $multiTotal2,
            'multiCanAddRound' => $multiCanAddRound,
            'multiCanFinalize' => $multiCanFinalize,
            'pickbanRequired' => $pickbanRequired,
            'pickbanLocked' => $pickbanLocked,
            'pickbanBlockingReport' => $pickbanBlockingReport,
            'pickbanState' => $pickbanState,
            'pickbanActions' => $pickbanActions,
            'pickbanSides' => $pickbanSides,
            'pickbanComputed' => $pickbanComputed,
            'pickbanMySlot' => $pickbanMySlot,
            'pickbanIsMyTurn' => $pickbanIsMyTurn,
            'pickbanStartMode' => $pickbanStartMode,
            'pickbanHigherSeedSlot' => $pickbanHigherSeedSlot,
            'pickbanSeedA' => $pickbanSeedA,
            'pickbanSeedB' => $pickbanSeedB,
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

        // Pick/Ban pending state: used by the bracket UI to show an "attention" marker.
        $pbByMatchId = [];
        $matchIds = [];
        foreach ($matches as $m) {
            $mid = (int)($m['id'] ?? 0);
            if ($mid > 0) {
                $matchIds[] = $mid;
            }
        }
        if ($matchIds !== []) {
            $pbRepo = new PickBanRepository();
            $pbByMatchId = $pbRepo->listStatesByMatchIds($matchIds);
        }

        $rulesetJson = is_string($tournament['ruleset_json'] ?? null) ? trim((string)$tournament['ruleset_json']) : '';
        $cfgByBo = [];
        if ($rulesetJson !== '') {
            $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
            $ruleset = $parsed['ruleset'] ?? null;
            if (is_array($ruleset)) {
                foreach ([1, 3, 5, 7, 9] as $bo) {
                    $cfgByBo[$bo] = PickBanEngine::buildMatchConfigSnapshot($ruleset, $bo);
                }
            }
        }

        foreach ($matches as $i => $m) {
            $mid = (int)($m['id'] ?? 0);
            $st = (string)($m['status'] ?? 'pending');
            if (in_array($st, ['confirmed', 'void'], true)) {
                $matches[$i]['pickban_pending'] = false;
                continue;
            }

            $matchComplete = $participantType === 'team'
                ? ($m['team1_id'] !== null && $m['team2_id'] !== null)
                : ($m['player1_id'] !== null && $m['player2_id'] !== null);

            if (!$matchComplete) {
                $matches[$i]['pickban_pending'] = false;
                continue;
            }

            $bestOf = (int)($m['best_of'] ?? 0);
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = (int)($tournament['best_of_default'] ?? 3);
            }
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = 3;
            }

            $required = $rulesetJson !== '' && array_key_exists($bestOf, $cfgByBo) && is_array($cfgByBo[$bestOf]);
            if (!$required) {
                $matches[$i]['pickban_pending'] = false;
                continue;
            }

            $state = $mid > 0 ? ($pbByMatchId[$mid] ?? null) : null;
            $locked = is_array($state) && ((string)($state['status'] ?? 'running')) === 'locked';
            $matches[$i]['pickban_pending'] = !$locked;
        }

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

    /** @param array<string, string> $params */
    public function live(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($id);
        if ($t === null) {
            Response::notFound();
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        $mRepo = new MatchRepository();
        $matches = $participantType === 'team'
            ? $mRepo->listTeamForTournament($id)
            : $mRepo->listSoloForTournament($id);

        $matchIds = [];
        foreach ($matches as $m) {
            $mid = (int)($m['id'] ?? 0);
            if ($mid > 0) {
                $matchIds[] = $mid;
            }
        }

        $pbByMatchId = [];
        if ($matchIds !== []) {
            $pbRepo = new PickBanRepository();
            $pbByMatchId = $pbRepo->listStatesByMatchIds($matchIds);
        }

        $rulesetJson = is_string($t['ruleset_json'] ?? null) ? trim((string)$t['ruleset_json']) : '';
        $cfgByBo = [];
        if ($rulesetJson !== '') {
            $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
            $ruleset = $parsed['ruleset'] ?? null;
            if (is_array($ruleset)) {
                foreach ([1, 3, 5, 7, 9] as $bo) {
                    $cfgByBo[$bo] = PickBanEngine::buildMatchConfigSnapshot($ruleset, $bo);
                }
            }
        }

        $out = [];
        foreach ($matches as $m) {
            $mid = (int)($m['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }

            $st = (string)($m['status'] ?? 'pending');
            $score1 = (int)($m['score1'] ?? 0);
            $score2 = (int)($m['score2'] ?? 0);
            $scheduledAt = is_string($m['scheduled_at'] ?? null) ? (string)$m['scheduled_at'] : '';
            $bestOf = (int)($m['best_of'] ?? 0);
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = (int)($t['best_of_default'] ?? 3);
            }
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = 3;
            }

            $reportedScore1 = $m['reported_score1'] ?? null;
            $reportedScore2 = $m['reported_score2'] ?? null;
            $reportedWinnerSlot = $m['reported_winner_slot'] ?? null;
            $reportedByUsername = (string)($m['reported_by_username'] ?? '');
            $reportedAt = is_string($m['reported_at'] ?? null) ? (string)$m['reported_at'] : '';

            $counterScore1 = $m['counter_reported_score1'] ?? null;
            $counterScore2 = $m['counter_reported_score2'] ?? null;
            $counterWinnerSlot = $m['counter_reported_winner_slot'] ?? null;
            $counterByUsername = (string)($m['counter_reported_by_username'] ?? '');
            $counterAt = is_string($m['counter_reported_at'] ?? null) ? (string)$m['counter_reported_at'] : '';

            if ($participantType === 'team') {
                $aId = $m['team1_id'] !== null ? (int)$m['team1_id'] : null;
                $bId = $m['team2_id'] !== null ? (int)$m['team2_id'] : null;
                $aName = (string)($m['t1_name'] ?? '');
                $bName = (string)($m['t2_name'] ?? '');
                $win = $m['winner_team_id'] !== null ? (int)$m['winner_team_id'] : null;
            } else {
                $aId = $m['player1_id'] !== null ? (int)$m['player1_id'] : null;
                $bId = $m['player2_id'] !== null ? (int)$m['player2_id'] : null;
                $aName = (string)($m['p1_name'] ?? '');
                $bName = (string)($m['p2_name'] ?? '');
                $win = $m['winner_id'] !== null ? (int)$m['winner_id'] : null;
            }

            $aLabel = $aId === null
                ? (($bId !== null && $win !== null && $win === $bId) ? 'BYE' : 'TBD')
                : ($aName !== '' ? $aName : '#');
            $bLabel = $bId === null
                ? (($aId !== null && $win !== null && $win === $aId) ? 'BYE' : 'TBD')
                : ($bName !== '' ? $bName : '#');

            $aWin = $win !== null && $aId !== null && $win === $aId;
            $bWin = $win !== null && $bId !== null && $win === $bId;
            $winnerSlot = $aWin ? 1 : ($bWin ? 2 : 0);

            $matchComplete = $aId !== null && $bId !== null;
            $isReported = in_array($st, ['reported', 'disputed'], true)
                && $matchComplete
                && ($reportedScore1 !== null) && ($reportedScore2 !== null);
            $showScores = (($st === 'confirmed') && $matchComplete) || $isReported;
            $cardS1 = $showScores ? ($isReported ? (string)(int)$reportedScore1 : (string)$score1) : '-';
            $cardS2 = $showScores ? ($isReported ? (string)(int)$reportedScore2 : (string)$score2) : '-';

            $required = false;
            $pending = false;
            if ($rulesetJson !== '' && $matchComplete && !in_array($st, ['confirmed', 'void'], true)) {
                $required = array_key_exists($bestOf, $cfgByBo) && is_array($cfgByBo[$bestOf]);
                if ($required) {
                    $state = $pbByMatchId[$mid] ?? null;
                    $locked = is_array($state) && ((string)($state['status'] ?? 'running')) === 'locked';
                    $pending = !$locked;
                }
            }

            $out[] = [
                'id' => $mid,
                'status' => $st,
                'best_of' => $bestOf,
                'scheduled_at' => $scheduledAt,
                'score1' => $score1,
                'score2' => $score2,
                'winner_slot' => $winnerSlot,
                'a_label' => $aLabel,
                'b_label' => $bLabel,
                'a_empty' => $aId === null,
                'b_empty' => $bId === null,
                'card_s1' => $cardS1,
                'card_s2' => $cardS2,
                'reported_score1' => $reportedScore1 !== null ? (int)$reportedScore1 : null,
                'reported_score2' => $reportedScore2 !== null ? (int)$reportedScore2 : null,
                'reported_winner_slot' => $reportedWinnerSlot !== null ? (int)$reportedWinnerSlot : null,
                'reported_by_username' => $reportedByUsername,
                'reported_at' => $reportedAt,
                'counter_reported_score1' => $counterScore1 !== null ? (int)$counterScore1 : null,
                'counter_reported_score2' => $counterScore2 !== null ? (int)$counterScore2 : null,
                'counter_reported_winner_slot' => $counterWinnerSlot !== null ? (int)$counterWinnerSlot : null,
                'counter_reported_by_username' => $counterByUsername,
                'counter_reported_at' => $counterAt,
                'pickban_required' => $required,
                'pickban_pending' => $pending,
            ];
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'tournament_id' => $id,
            'participant_type' => $participantType,
            'matches' => $out,
            'generated_at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
