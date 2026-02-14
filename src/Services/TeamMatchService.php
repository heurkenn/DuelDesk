<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\MatchTeamDuelRepository;
use DuelDesk\Repositories\MatchTeamLineupRepository;
use DuelDesk\Repositories\TeamMemberRepository;

final class TeamMatchService
{
    /**
     * Ensure regular duels exist once both lineups are set.
     *
     * @param array<string,mixed> $tournament
     * @param array<string,mixed> $match Detailed match row (team1_id/team2_id required)
     */
    public function ensureRegularDuels(array $tournament, array $match): void
    {
        $mode = (string)($tournament['team_match_mode'] ?? 'standard');
        $ptype = (string)($tournament['participant_type'] ?? 'solo');
        if ($ptype !== 'team' || $mode !== 'lineup_duels') {
            return;
        }

        $matchId = (int)($match['id'] ?? 0);
        $teamSize = (int)($tournament['team_size'] ?? 0);
        $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
        $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
        if ($matchId <= 0 || $teamSize <= 0 || $t1 <= 0 || $t2 <= 0) {
            return;
        }

        $duelRepo = new MatchTeamDuelRepository();
        if ($duelRepo->countDuels($matchId) > 0) {
            return;
        }

        $lineupRepo = new MatchTeamLineupRepository();
        $l1 = $lineupRepo->listLineup($matchId, 1);
        $l2 = $lineupRepo->listLineup($matchId, 2);
        if (count($l1) !== $teamSize || count($l2) !== $teamSize) {
            return;
        }

        $duels = [];
        for ($i = 1; $i <= $teamSize; $i++) {
            $u1 = (int)($l1[$i - 1]['user_id'] ?? 0);
            $u2 = (int)($l2[$i - 1]['user_id'] ?? 0);
            if ($u1 <= 0 || $u2 <= 0) {
                return;
            }
            $duels[] = [
                'kind' => 'regular',
                'duel_index' => $i,
                'team1_user_id' => $u1,
                'team2_user_id' => $u2,
            ];
        }

        $duelRepo->insertDuels($matchId, $duels);
    }

    /**
     * After a duel is confirmed, check if the parent match can be confirmed.
     *
     * @param array<string,mixed> $tournament
     * @param array<string,mixed> $match Detailed match row (team1_id/team2_id required)
     */
    public function maybeFinalizeMatch(array $tournament, array $match): void
    {
        $mode = (string)($tournament['team_match_mode'] ?? 'standard');
        $ptype = (string)($tournament['participant_type'] ?? 'solo');
        if ($ptype !== 'team' || $mode !== 'lineup_duels') {
            return;
        }

        $status = (string)($match['status'] ?? 'pending');
        if (in_array($status, ['confirmed', 'void'], true)) {
            return;
        }

        $matchId = (int)($match['id'] ?? 0);
        $teamSize = (int)($tournament['team_size'] ?? 0);
        $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
        $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
        if ($matchId <= 0 || $teamSize <= 0 || $t1 <= 0 || $t2 <= 0) {
            return;
        }

        $duelRepo = new MatchTeamDuelRepository();
        $duels = $duelRepo->listDuels($matchId);

        $regular = array_values(array_filter($duels, static fn ($d) => is_array($d) && (string)($d['kind'] ?? '') === 'regular'));
        if (count($regular) < $teamSize) {
            return;
        }

        $wins1 = 0;
        $wins2 = 0;
        $confirmedRegular = 0;

        foreach ($regular as $d) {
            $st = (string)($d['status'] ?? 'pending');
            if ($st !== 'confirmed') {
                continue;
            }
            $confirmedRegular++;
            $w = $d['winner_slot'] ?? null;
            $w = (is_int($w) || is_string($w)) ? (int)$w : 0;
            if ($w === 1) {
                $wins1++;
            } elseif ($w === 2) {
                $wins2++;
            }
        }

        if ($confirmedRegular < $teamSize) {
            return;
        }

        // Odd team sizes cannot tie, but even sizes can.
        if ($wins1 === $wins2) {
            $this->ensureCaptainTiebreakerDuel($tournament, $match);

            $duels = $duelRepo->listDuels($matchId);
            $tb = null;
            foreach ($duels as $d) {
                if (!is_array($d)) continue;
                if ((string)($d['kind'] ?? '') === 'captain_tiebreak') {
                    $tb = $d;
                    break;
                }
            }
            if (!is_array($tb) || (string)($tb['status'] ?? 'pending') !== 'confirmed') {
                return;
            }

            $w = $tb['winner_slot'] ?? null;
            $w = (is_int($w) || is_string($w)) ? (int)$w : 0;
            if ($w === 1) {
                $wins1++;
            } elseif ($w === 2) {
                $wins2++;
            } else {
                return;
            }
        }

        $winnerSlot = $wins1 > $wins2 ? 1 : 2;

        // Confirm the parent match using duel wins as the score (keeps bracket logic intact).
        $mRepo = new MatchRepository();
        $base = $mRepo->findById($matchId);
        if (!is_array($base)) {
            return;
        }
        $svc = new MatchResultService();
        $svc->confirmAndAdvance($tournament, $base, $wins1, $wins2, $winnerSlot, requirePickbanLocked: true);
    }

    /**
     * Ensure the captain tiebreaker duel exists (if needed).
     *
     * @param array<string,mixed> $tournament
     * @param array<string,mixed> $match
     */
    private function ensureCaptainTiebreakerDuel(array $tournament, array $match): void
    {
        $matchId = (int)($match['id'] ?? 0);
        if ($matchId <= 0) {
            return;
        }

        $duelRepo = new MatchTeamDuelRepository();
        $duels = $duelRepo->listDuels($matchId);
        foreach ($duels as $d) {
            if (!is_array($d)) continue;
            if ((string)($d['kind'] ?? '') === 'captain_tiebreak') {
                return;
            }
        }

        $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
        $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
        if ($t1 <= 0 || $t2 <= 0) {
            return;
        }

        $tmRepo = new TeamMemberRepository();
        $m1 = $tmRepo->listMembers($t1);
        $m2 = $tmRepo->listMembers($t2);

        $c1 = $this->resolveCaptainUserId($m1);
        $c2 = $this->resolveCaptainUserId($m2);
        if ($c1 <= 0 || $c2 <= 0) {
            return;
        }

        $teamSize = (int)($tournament['team_size'] ?? 0);
        if ($teamSize <= 0) {
            $teamSize = 2;
        }

        $duelRepo->insertDuels($matchId, [[
            'kind' => 'captain_tiebreak',
            'duel_index' => $teamSize + 1,
            'team1_user_id' => $c1,
            'team2_user_id' => $c2,
        ]]);
    }

    /** @param list<array<string,mixed>> $members */
    private function resolveCaptainUserId(array $members): int
    {
        foreach ($members as $m) {
            if (!is_array($m)) continue;
            if ((string)($m['role'] ?? '') === 'captain') {
                return (int)($m['user_id'] ?? 0);
            }
        }
        // Fallback: first member.
        foreach ($members as $m) {
            if (!is_array($m)) continue;
            $uid = (int)($m['user_id'] ?? 0);
            if ($uid > 0) {
                return $uid;
            }
        }
        return 0;
    }
}

