<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PickBanRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\UserRepository;
use DuelDesk\Services\MatchResultService;
use DuelDesk\Services\PickBanEngine;
use DuelDesk\Support\BotApi;

final class BotApiController
{
    private function requireAdminDiscord(string $discordUserId): array
    {
        $discordUserId = trim($discordUserId);
        if ($discordUserId === '') {
            $this->json(400, ['ok' => false, 'error' => 'Missing discord_user_id']);
        }

        $uRepo = new UserRepository();
        $u = $uRepo->findByDiscordUserId($discordUserId);
        if (!is_array($u) || ((string)($u['role'] ?? 'user')) !== 'admin') {
            $this->json(403, ['ok' => false, 'error' => 'Forbidden']);
        }

        return $u;
    }

    /** @param array<string, string> $params */
    public function reportMatch(array $params = []): void
    {
        BotApi::requireAuth();

        $raw = file_get_contents('php://input');
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $this->json(400, ['ok' => false, 'error' => 'Invalid JSON']);
        }

        $discordUserId = is_string($data['discord_user_id'] ?? null) ? trim((string)$data['discord_user_id']) : '';
        $tournamentId = (int)($data['tournament_id'] ?? 0);
        $matchId = (int)($data['match_id'] ?? 0);
        $winnerSlot = (int)($data['winner_slot'] ?? 0);
        $score1 = (int)($data['score1'] ?? -1);
        $score2 = (int)($data['score2'] ?? -1);

        if ($discordUserId === '' || $tournamentId <= 0 || $matchId <= 0) {
            $this->json(400, ['ok' => false, 'error' => 'Missing parameters']);
        }

        $this->requireAdminDiscord($discordUserId);

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            $this->json(404, ['ok' => false, 'error' => 'Tournament not found']);
        }
        if (((string)($t['participant_type'] ?? 'solo')) === 'team' && in_array((string)($t['team_match_mode'] ?? 'standard'), ['lineup_duels', 'multi_round'], true)) {
            $this->json(400, ['ok' => false, 'error' => "Team match mode not supported by bot report."]);
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if ($match === null || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            $this->json(404, ['ok' => false, 'error' => 'Match not found']);
        }

        try {
            $svc = new MatchResultService();
            $res = $svc->confirmAndAdvance($t, $match, $score1, $score2, $winnerSlot, requirePickbanLocked: true);
        } catch (\Throwable $e) {
            $this->json(400, ['ok' => false, 'error' => $e->getMessage()]);
        }

        $this->json(200, [
            'ok' => true,
            'tournament_id' => $tournamentId,
            'match_id' => $matchId,
            'result' => $res,
        ]);
    }

    /** @param array<string, string> $params */
    public function reportTournaments(array $params = []): void
    {
        BotApi::requireAuth();

        $discordUserId = is_string($_GET['discord_user_id'] ?? null) ? (string)$_GET['discord_user_id'] : '';
        $this->requireAdminDiscord($discordUserId);

        $tRepo = new TournamentRepository();
        $all = $tRepo->all();

        $out = [];
        foreach ($all as $t) {
            $status = (string)($t['status'] ?? 'draft');
            if (!in_array($status, ['published', 'running'], true)) {
                continue;
            }

            $tid = (int)($t['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }

            if (($t['participant_type'] ?? 'solo') === 'team') {
                $mm = (string)($t['team_match_mode'] ?? 'standard');
                if (in_array($mm, ['lineup_duels', 'multi_round'], true)) {
                    continue; // bot report doesn't support these modes
                }
            }

            // Include tournament if it has at least one unconfirmed (non-void) complete match.
            $ptype = (string)($t['participant_type'] ?? 'solo');
            if (!in_array($ptype, ['solo', 'team'], true)) {
                $ptype = 'solo';
            }

            $mRepo = new MatchRepository();
            $matches = $ptype === 'team' ? $mRepo->listTeamForTournament($tid) : $mRepo->listSoloForTournament($tid);

            $has = false;
            foreach ($matches as $m) {
                $st = (string)($m['status'] ?? 'pending');
                if (in_array($st, ['confirmed', 'void'], true)) {
                    continue;
                }
                $complete = $ptype === 'team'
                    ? (($m['team1_id'] ?? null) !== null && ($m['team2_id'] ?? null) !== null)
                    : (($m['player1_id'] ?? null) !== null && ($m['player2_id'] ?? null) !== null);
                if ($complete) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                continue;
            }

            $out[] = [
                'id' => $tid,
                'name' => (string)($t['name'] ?? ('#' . $tid)),
                'status' => $status,
            ];

            if (count($out) >= 30) {
                break;
            }
        }

        $this->json(200, [
            'ok' => true,
            'tournaments' => $out,
        ]);
    }

    /** @param array<string, string> $params */
    public function reportMatches(array $params = []): void
    {
        BotApi::requireAuth();

        $discordUserId = is_string($_GET['discord_user_id'] ?? null) ? (string)$_GET['discord_user_id'] : '';
        $this->requireAdminDiscord($discordUserId);

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            $this->json(404, ['ok' => false, 'error' => 'Tournament not found']);
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            $this->json(404, ['ok' => false, 'error' => 'Tournament not found']);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if (!in_array($participantType, ['solo', 'team'], true)) {
            $participantType = 'solo';
        }

        $mRepo = new MatchRepository();
        $matches = $participantType === 'team'
            ? $mRepo->listTeamForTournament($tournamentId)
            : $mRepo->listSoloForTournament($tournamentId);

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
        $ruleset = null;
        if ($rulesetJson !== '') {
            $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
            if (is_array($parsed['ruleset'] ?? null)) {
                $ruleset = $parsed['ruleset'];
            }
        }

        $reportable = [];
        $blockedPickban = 0;

        foreach ($matches as $m) {
            $mid = (int)($m['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }

            $st = (string)($m['status'] ?? 'pending');
            if (in_array($st, ['confirmed', 'void'], true)) {
                continue;
            }

            $complete = $participantType === 'team'
                ? (($m['team1_id'] ?? null) !== null && ($m['team2_id'] ?? null) !== null)
                : (($m['player1_id'] ?? null) !== null && ($m['player2_id'] ?? null) !== null);
            if (!$complete) {
                continue;
            }

            $bestOf = (int)($m['best_of'] ?? 0);
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = (int)($t['best_of_default'] ?? 3);
            }
            if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                $bestOf = 3;
            }

            $pickbanRequired = false;
            $pickbanLocked = false;
            if (is_array($ruleset)) {
                $cfg = PickBanEngine::buildMatchConfigSnapshot($ruleset, $bestOf);
                $pickbanRequired = $cfg !== null;
                if ($pickbanRequired) {
                    $state = $pbByMatchId[$mid] ?? null;
                    $pickbanLocked = is_array($state) && ((string)($state['status'] ?? 'running')) === 'locked';
                }
            }

            if ($pickbanRequired && !$pickbanLocked) {
                $blockedPickban++;
                continue;
            }

            $a = $participantType === 'team' ? (string)($m['t1_name'] ?? '') : (string)($m['p1_name'] ?? '');
            $b = $participantType === 'team' ? (string)($m['t2_name'] ?? '') : (string)($m['p2_name'] ?? '');
            $tag = strtoupper(substr((string)($m['bracket'] ?? 'winners'), 0, 1)) . (int)($m['round'] ?? 0) . '#' . (int)($m['round_pos'] ?? 0);

            $reportable[] = [
                'id' => $mid,
                'status' => $st,
                'best_of' => $bestOf,
                'a' => $a !== '' ? $a : 'A',
                'b' => $b !== '' ? $b : 'B',
                'tag' => $tag,
            ];

            if (count($reportable) >= 40) {
                break;
            }
        }

        $this->json(200, [
            'ok' => true,
            'tournament' => [
                'id' => $tournamentId,
                'name' => (string)($t['name'] ?? ('#' . $tournamentId)),
            ],
            'blocked_pickban' => $blockedPickban,
            'matches' => $reportable,
        ]);
    }

    /** @param array<string, string> $params */
    public function pickbanPending(array $params = []): void
    {
        BotApi::requireAuth();

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->all(); // will include LAN tournaments too

        $tasks = [];

        foreach ($tournaments as $t) {
            $rulesetJson = is_string($t['ruleset_json'] ?? null) ? trim((string)$t['ruleset_json']) : '';
            if ($rulesetJson === '') {
                continue;
            }

            $tournamentId = (int)($t['id'] ?? 0);
            if ($tournamentId <= 0) {
                continue;
            }

            $participantType = (string)($t['participant_type'] ?? 'solo');
            if (!in_array($participantType, ['solo', 'team'], true)) {
                $participantType = 'solo';
            }

            $startMode = (string)($t['pickban_start_mode'] ?? 'coin_toss');
            if (!in_array($startMode, ['coin_toss', 'higher_seed'], true)) {
                $startMode = 'coin_toss';
            }

            $mRepo = new MatchRepository();
            $matches = $participantType === 'team'
                ? $mRepo->listTeamForTournament($tournamentId)
                : $mRepo->listSoloForTournament($tournamentId);

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

            $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
            $ruleset = is_array($parsed['ruleset'] ?? null) ? $parsed['ruleset'] : null;
            if (!is_array($ruleset)) {
                continue;
            }

            foreach ($matches as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }

                $st = (string)($m['status'] ?? 'pending');
                if (in_array($st, ['confirmed', 'void'], true)) {
                    continue;
                }

                $bestOf = (int)($m['best_of'] ?? 0);
                if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                    $bestOf = (int)($t['best_of_default'] ?? 3);
                }
                if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
                    $bestOf = 3;
                }

                $cfg = PickBanEngine::buildMatchConfigSnapshot($ruleset, $bestOf);
                if ($cfg === null) {
                    continue; // not supported for this BO
                }

                $complete = $this->isMatchComplete($participantType, $m);
                if (!$complete) {
                    continue;
                }

                $state = $pbByMatchId[$mid] ?? null;
                $locked = is_array($state) && ((string)($state['status'] ?? 'running')) === 'locked';
                if ($locked) {
                    continue;
                }

                $actions = [];
                $sides = [];
                $firstTurnSlot = null;

                if (is_array($state)) {
                    $first = (int)($state['first_turn_slot'] ?? 0);
                    $firstTurnSlot = ($first === 1 || $first === 2) ? $first : null;

                    $pbRepo = new PickBanRepository();
                    $actions = $pbRepo->listActions($mid);
                    $sides = $pbRepo->listSides($mid);
                } else {
                    // Not started yet.
                    $firstTurnSlot = null;
                }

                $computed = PickBanEngine::compute($cfg, $firstTurnSlot, $actions, $sides);
                if (!is_array($computed) || !($computed['ok'] ?? false)) {
                    continue;
                }

                $nextStep = is_string($computed['next_step'] ?? null) ? (string)$computed['next_step'] : null;
                $nextSlot = $computed['next_slot'] ?? null;
                $nextSlot = ($nextSlot === 1 || $nextSlot === 2) ? (int)$nextSlot : null;

                // Convert engine state into a "who should act" hint.
                $notifySlots = [];
                $needsCoinToss = (bool)($computed['needs_coin_toss'] ?? false);
                if ($state === null) {
                    if ($startMode === 'higher_seed') {
                        // higher_seed: only higher seed chooses Team A/B to start the flow.
                        $hs = $this->resolveHigherSeedSlot($participantType, $tournamentId, $m);
                        if ($hs !== null) {
                            $notifySlots = [$hs];
                            $nextStep = 'start';
                            $nextSlot = $hs;
                        } else {
                            $notifySlots = [1, 2];
                            $nextStep = 'start';
                        }
                    } else {
                        // coin toss mode: either slot can start the coin toss.
                        $notifySlots = [1, 2];
                        $nextStep = 'coin_toss';
                    }
                } elseif ($needsCoinToss) {
                    $notifySlots = [1, 2];
                    $nextStep = 'coin_toss';
                } elseif ($nextStep === 'side' && $nextSlot !== null) {
                    $notifySlots = [$nextSlot];
                } elseif (in_array($nextStep, ['ban', 'pick'], true) && $nextSlot !== null) {
                    $notifySlots = [$nextSlot];
                } else {
                    continue;
                }

                $discordUserIds = $this->resolveDiscordUsersForSlots($participantType, $m, $notifySlots);
                if ($discordUserIds === []) {
                    continue;
                }

                $tSlug = is_string($t['slug'] ?? null) ? trim((string)$t['slug']) : '';
                $lanSlug = is_string($t['lan_event_slug'] ?? null) ? trim((string)$t['lan_event_slug']) : '';
                $matchUrl = '';
                if ($tournamentId > 0 && $mid > 0) {
                    // Match detail is the most actionable page (pick/ban + reporting).
                    // We don't currently have a LAN-scoped match route.
                    $matchUrl = '/tournaments/' . $tournamentId . '/matches/' . $mid;
                }

                $labelA = $participantType === 'team' ? (string)($m['t1_name'] ?? '') : (string)($m['p1_name'] ?? '');
                $labelB = $participantType === 'team' ? (string)($m['t2_name'] ?? '') : (string)($m['p2_name'] ?? '');

                $tasks[] = [
                    'task_key' => 'pickban:' . $mid . ':' . (int)($computed['next_index'] ?? 0) . ':' . (string)$nextStep . ':' . (string)($nextSlot ?? 'any'),
                    'tournament_id' => $tournamentId,
                    'tournament_name' => (string)($t['name'] ?? ''),
                    'match_id' => $mid,
                    'match_url' => $matchUrl,
                    'participant_type' => $participantType,
                    'best_of' => $bestOf,
                    'next_step' => $nextStep,
                    'next_slot' => $nextSlot,
                    'needs_coin_toss' => $needsCoinToss,
                    'a' => $labelA,
                    'b' => $labelB,
                    'discord_user_ids' => $discordUserIds,
                ];
            }
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'tasks' => $tasks,
            'generated_at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string,mixed> $payload */
    private function json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param 'solo'|'team' $participantType
     * @param array<string,mixed> $match
     */
    private function isMatchComplete(string $participantType, array $match): bool
    {
        if ($participantType === 'team') {
            return ($match['team1_id'] ?? null) !== null && ($match['team2_id'] ?? null) !== null;
        }
        return ($match['player1_id'] ?? null) !== null && ($match['player2_id'] ?? null) !== null;
    }

    /**
     * @param 'solo'|'team' $participantType
     * @param array<string,mixed> $match
     * @param list<int> $slots
     * @return list<string> Discord user IDs
     */
    private function resolveDiscordUsersForSlots(string $participantType, array $match, array $slots): array
    {
        $slots = array_values(array_unique(array_filter($slots, static fn ($v) => is_int($v) && ($v === 1 || $v === 2))));
        if ($slots === []) {
            return [];
        }

        $userIds = [];

        if ($participantType === 'team') {
            $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;

            $teamIds = [];
            foreach ($slots as $s) {
                if ($s === 1 && $t1 > 0) {
                    $teamIds[] = $t1;
                } elseif ($s === 2 && $t2 > 0) {
                    $teamIds[] = $t2;
                }
            }

            if ($teamIds !== []) {
                $tmRepo = new TeamMemberRepository();
                $membersByTeam = $tmRepo->listMembersForTeams($teamIds);
                foreach ($teamIds as $tid) {
                    foreach ($membersByTeam[$tid] ?? [] as $m) {
                        if (($m['role'] ?? '') === 'captain') {
                            $uid = (int)($m['user_id'] ?? 0);
                            if ($uid > 0) {
                                $userIds[$uid] = true;
                            }
                            break;
                        }
                    }
                }
            }
        } else {
            $pRepo = new PlayerRepository();
            $p1 = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
            $p2 = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;

            foreach ($slots as $s) {
                $pid = ($s === 1) ? $p1 : $p2;
                if ($pid <= 0) {
                    continue;
                }
                $uid = $pRepo->findUserIdByPlayerId($pid);
                if ($uid !== null) {
                    $userIds[(int)$uid] = true;
                }
            }
        }

        if ($userIds === []) {
            return [];
        }

        $uRepo = new UserRepository();
        // Important: Discord snowflakes are numeric strings. If we use them as PHP array keys directly,
        // PHP will coerce numeric-string keys to int, json_encode() will emit JSON numbers, and JS will
        // lose precision (>2^53). Keep them as strings end-to-end.
        $discordIds = [];
        foreach (array_keys($userIds) as $uid) {
            $u = $uRepo->findById((int)$uid);
            $did = is_array($u) && is_string($u['discord_user_id'] ?? null) ? trim((string)$u['discord_user_id']) : '';
            if ($did !== '') {
                $discordIds['d:' . $did] = $did; // non-numeric key prevents int coercion
            }
        }

        return array_values($discordIds);
    }

    /**
     * @param 'solo'|'team' $participantType
     * @param array<string,mixed> $match
     */
    private function resolveHigherSeedSlot(string $participantType, int $tournamentId, array $match): ?int
    {
        if ($participantType === 'team') {
            $aId = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $bId = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($aId <= 0 || $bId <= 0) {
                return null;
            }
            $ttRepo = new \DuelDesk\Repositories\TournamentTeamRepository();
            $seedA = $ttRepo->findSeed($tournamentId, $aId);
            $seedB = $ttRepo->findSeed($tournamentId, $bId);
        } else {
            $aId = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
            $bId = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
            if ($aId <= 0 || $bId <= 0) {
                return null;
            }
            $tpRepo = new \DuelDesk\Repositories\TournamentPlayerRepository();
            $seedA = $tpRepo->findSeed($tournamentId, $aId);
            $seedB = $tpRepo->findSeed($tournamentId, $bId);
        }

        if ($seedA !== null && $seedB !== null) {
            return $seedA <= $seedB ? 1 : 2;
        }
        if ($seedA !== null) {
            return 1;
        }
        if ($seedB !== null) {
            return 2;
        }
        return 1;
    }
}
