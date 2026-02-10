<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentTeamRepository;

final class BracketGenerator
{
    public function __construct(
        private readonly MatchRepository $mRepo = new MatchRepository(),
        private readonly TournamentPlayerRepository $tpRepo = new TournamentPlayerRepository(),
        private readonly TournamentTeamRepository $ttRepo = new TournamentTeamRepository(),
    ) {
    }

    /**
     * Generate a single elimination bracket for a tournament.
     *
     * @param 'solo'|'team' $participantType
     */
    public function generateSingleElim(int $tournamentId, string $participantType, int $bestOf = 3): void
    {
        $entrants = $participantType === 'team'
            ? $this->listTeamEntrantsBySeed($tournamentId)
            : $this->listSoloEntrantsBySeed($tournamentId);

        $n = count($entrants);
        if ($n < 2) {
            throw new \RuntimeException('Not enough entrants to generate bracket');
        }

        $bracketSize = $this->nextPow2($n);
        $rounds = (int)log($bracketSize, 2);
        if ($rounds <= 0) {
            throw new \RuntimeException('Invalid bracket size');
        }

        $seedPositions = $this->seedPositions($bracketSize);

        // Slot order follows seedPositions. Each slot gets entrant rank (1..n) or null (bye).
        $slots = array_fill(0, $bracketSize, null);
        for ($i = 0; $i < $bracketSize; $i++) {
            $seed = $seedPositions[$i] ?? ($bracketSize + 1);
            if ($seed >= 1 && $seed <= $n) {
                $slots[$i] = $entrants[$seed - 1];
            }
        }

        // ids[round][pos] = match_id
        $ids = [];
        // parts[round][pos] = [id1,id2]
        $parts = [];

        for ($r = 1; $r <= $rounds; $r++) {
            $matchesInRound = (int)($bracketSize / (2 ** $r));
            $ids[$r] = [];
            $parts[$r] = [];

            for ($pos = 1; $pos <= $matchesInRound; $pos++) {
                $a = null;
                $b = null;
                if ($r === 1) {
                    $a = $slots[(($pos - 1) * 2)] ?? null;
                    $b = $slots[(($pos - 1) * 2) + 1] ?? null;
                }

                $matchId = $participantType === 'team'
                    ? $this->mRepo->createTeam($tournamentId, 'winners', $r, $pos, $bestOf, $a, $b)
                    : $this->mRepo->createSolo($tournamentId, 'winners', $r, $pos, $bestOf, $a, $b);

                $ids[$r][$pos] = $matchId;
                $parts[$r][$pos] = [$a, $b];
            }
        }

        // Auto-advance byes across rounds.
        for ($r = 1; $r <= $rounds; $r++) {
            $matchesInRound = (int)($bracketSize / (2 ** $r));
            for ($pos = 1; $pos <= $matchesInRound; $pos++) {
                [$a, $b] = $parts[$r][$pos] ?? [null, null];
                $winner = null;

                if ($a !== null && $b === null) {
                    $winner = $a;
                } elseif ($a === null && $b !== null) {
                    $winner = $b;
                }

                if ($winner === null) {
                    continue;
                }

                $matchId = (int)($ids[$r][$pos] ?? 0);
                if ($matchId <= 0) {
                    continue;
                }

                if ($participantType === 'team') {
                    $this->mRepo->confirmTeamWinner($matchId, $winner);
                } else {
                    $this->mRepo->confirmSoloWinner($matchId, $winner);
                }

                if ($r >= $rounds) {
                    continue;
                }

                $nextRound = $r + 1;
                $nextPos = (int)(($pos + 1) / 2);
                $nextSlot = ($pos % 2 === 1) ? 1 : 2;
                $nextMatchId = (int)($ids[$nextRound][$nextPos] ?? 0);

                if ($nextMatchId <= 0) {
                    continue;
                }

                // Update in-memory participants.
                $parts[$nextRound][$nextPos] ??= [null, null];
                $parts[$nextRound][$nextPos][$nextSlot - 1] = $winner;

                if ($participantType === 'team') {
                    $this->mRepo->setTeamSlot($nextMatchId, $nextSlot, $winner);
                } else {
                    $this->mRepo->setSoloSlot($nextMatchId, $nextSlot, $winner);
                }
            }
        }
    }

    /**
     * Generate a round robin schedule using the circle method.
     *
     * - Creates all matches upfront.
     * - If there is an odd number of entrants, one "BYE" per round is skipped (no match row).
     *
     * @param 'solo'|'team' $participantType
     */
    public function generateRoundRobin(int $tournamentId, string $participantType, int $bestOf = 3): void
    {
        $entrants = $participantType === 'team'
            ? $this->listTeamEntrantsBySeed($tournamentId)
            : $this->listSoloEntrantsBySeed($tournamentId);

        $n = count($entrants);
        if ($n < 2) {
            throw new \RuntimeException('Not enough entrants to generate schedule');
        }

        /** @var list<int|null> $arr */
        $arr = $entrants;
        if (($n % 2) === 1) {
            $arr[] = null; // BYE placeholder
        }

        $size = count($arr);
        if ($size < 2) {
            throw new \RuntimeException('Invalid entrant count');
        }

        $rounds = $size - 1;
        $half = (int)($size / 2);

        for ($round = 1; $round <= $rounds; $round++) {
            $pos = 1;

            for ($i = 0; $i < $half; $i++) {
                $a = $arr[$i] ?? null;
                $b = $arr[$size - 1 - $i] ?? null;
                if ($a === null || $b === null) {
                    continue; // BYE
                }

                // Small home/away shuffle to avoid always placing the fixed player on the same side.
                if (($round % 2) === 0 && $i === 0) {
                    [$a, $b] = [$b, $a];
                }

                if ($participantType === 'team') {
                    $this->mRepo->createTeam($tournamentId, 'round_robin', $round, $pos, $bestOf, $a, $b);
                } else {
                    $this->mRepo->createSolo($tournamentId, 'round_robin', $round, $pos, $bestOf, $a, $b);
                }
                $pos++;
            }

            // Rotate all but the first element (circle method).
            $fixed = $arr[0] ?? null;
            $rest = array_slice($arr, 1);
            if ($rest === []) {
                break;
            }
            $last = array_pop($rest);
            array_unshift($rest, $last);
            $arr = array_merge([$fixed], $rest);
        }
    }

    /** @return list<int> */
    private function listSoloEntrantsBySeed(int $tournamentId): array
    {
        // listForTournament is ordered: seeded first (seed ASC), then unseeded (joined_at ASC).
        $rows = $this->tpRepo->listForTournament($tournamentId);

        $all = [];
        $seeded = [];
        $unseeded = [];

        foreach ($rows as $r) {
            $id = (int)($r['player_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $all[] = $id;

            $seed = $r['seed'] ?? null;
            if ($seed !== null) {
                $s = (int)$seed;
                $seeded[$s] = $id;
            } else {
                $unseeded[] = $id;
            }
        }

        $n = count($all);
        if ($n <= 0) {
            return [];
        }

        foreach (array_keys($seeded) as $s) {
            if (!is_int($s) || $s < 1 || $s > $n) {
                throw new \RuntimeException('Invalid seeds (must be between 1 and participant count)');
            }
        }

        $entrants = [];
        for ($s = 1; $s <= $n; $s++) {
            if (isset($seeded[$s])) {
                $entrants[] = (int)$seeded[$s];
                continue;
            }

            $next = array_shift($unseeded);
            if (!is_int($next) || $next <= 0) {
                throw new \RuntimeException('Seed assignment failed');
            }
            $entrants[] = $next;
        }

        if (count(array_unique($entrants)) !== $n) {
            throw new \RuntimeException('Invalid seeds (duplicates)');
        }

        return $entrants;
    }

    /** @return list<int> */
    private function listTeamEntrantsBySeed(int $tournamentId): array
    {
        $rows = $this->ttRepo->listForTournament($tournamentId);

        $all = [];
        $seeded = [];
        $unseeded = [];

        foreach ($rows as $r) {
            $id = (int)($r['team_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $all[] = $id;

            $seed = $r['seed'] ?? null;
            if ($seed !== null) {
                $s = (int)$seed;
                $seeded[$s] = $id;
            } else {
                $unseeded[] = $id;
            }
        }

        $n = count($all);
        if ($n <= 0) {
            return [];
        }

        foreach (array_keys($seeded) as $s) {
            if (!is_int($s) || $s < 1 || $s > $n) {
                throw new \RuntimeException('Invalid seeds (must be between 1 and participant count)');
            }
        }

        $entrants = [];
        for ($s = 1; $s <= $n; $s++) {
            if (isset($seeded[$s])) {
                $entrants[] = (int)$seeded[$s];
                continue;
            }

            $next = array_shift($unseeded);
            if (!is_int($next) || $next <= 0) {
                throw new \RuntimeException('Seed assignment failed');
            }
            $entrants[] = $next;
        }

        if (count(array_unique($entrants)) !== $n) {
            throw new \RuntimeException('Invalid seeds (duplicates)');
        }

        return $entrants;
    }

    private function nextPow2(int $n): int
    {
        $p = 1;
        while ($p < $n) {
            $p <<= 1;
            if ($p > (1 << 30)) {
                break;
            }
        }
        return $p;
    }

    /** @return list<int> */
    private function seedPositions(int $size): array
    {
        $positions = [1];
        $s = 1;

        while ($s < $size) {
            $next = [];
            foreach ($positions as $x) {
                $next[] = $x;
                $next[] = (2 * $s + 1) - $x;
            }
            $positions = $next;
            $s *= 2;
        }

        return $positions;
    }

    /**
     * Generate a double elimination bracket for a tournament.
     *
     * Notes:
     * - Creates all matches upfront (including losers + grand final), even if participants are TBD.
     * - Byes are auto-advanced in the winners bracket only.
     *
     * @param 'solo'|'team' $participantType
     */
    public function generateDoubleElim(int $tournamentId, string $participantType, int $bestOf = 3): void
    {
        $entrants = $participantType === 'team'
            ? $this->listTeamEntrantsBySeed($tournamentId)
            : $this->listSoloEntrantsBySeed($tournamentId);

        $n = count($entrants);
        if ($n < 2) {
            throw new \RuntimeException('Not enough entrants to generate bracket');
        }

        $bracketSize = $this->nextPow2($n);
        $rounds = (int)log($bracketSize, 2);
        if ($rounds <= 0) {
            throw new \RuntimeException('Invalid bracket size');
        }

        $seedPositions = $this->seedPositions($bracketSize);

        $slots = array_fill(0, $bracketSize, null);
        for ($i = 0; $i < $bracketSize; $i++) {
            $seed = $seedPositions[$i] ?? ($bracketSize + 1);
            if ($seed >= 1 && $seed <= $n) {
                $slots[$i] = $entrants[$seed - 1];
            }
        }

        // Winners bracket matches.
        $wIds = [];
        $wParts = [];

        for ($r = 1; $r <= $rounds; $r++) {
            $matchesInRound = (int)($bracketSize / (2 ** $r));
            $wIds[$r] = [];
            $wParts[$r] = [];

            for ($pos = 1; $pos <= $matchesInRound; $pos++) {
                $a = null;
                $b = null;
                if ($r === 1) {
                    $a = $slots[(($pos - 1) * 2)] ?? null;
                    $b = $slots[(($pos - 1) * 2) + 1] ?? null;
                }

                $matchId = $participantType === 'team'
                    ? $this->mRepo->createTeam($tournamentId, 'winners', $r, $pos, $bestOf, $a, $b)
                    : $this->mRepo->createSolo($tournamentId, 'winners', $r, $pos, $bestOf, $a, $b);

                $wIds[$r][$pos] = $matchId;
                $wParts[$r][$pos] = [$a, $b];
            }
        }

        // Losers bracket skeleton.
        $losersRounds = (2 * $rounds) - 2;
        for ($lr = 1; $lr <= $losersRounds; $lr++) {
            $matchesInRound = (int)($bracketSize / (2 ** (int)(ceil($lr / 2) + 1)));
            for ($pos = 1; $pos <= $matchesInRound; $pos++) {
                if ($participantType === 'team') {
                    $this->mRepo->createTeam($tournamentId, 'losers', $lr, $pos, $bestOf, null, null);
                } else {
                    $this->mRepo->createSolo($tournamentId, 'losers', $lr, $pos, $bestOf, null, null);
                }
            }
        }

        // Grand final skeleton.
        $grandId = $participantType === 'team'
            ? $this->mRepo->createTeam($tournamentId, 'grand', 1, 1, $bestOf, null, null)
            : $this->mRepo->createSolo($tournamentId, 'grand', 1, 1, $bestOf, null, null);

        // Optional bracket reset grand final (GF2). Participants are filled only if needed.
        if ($participantType === 'team') {
            $this->mRepo->createTeam($tournamentId, 'grand', 2, 1, $bestOf, null, null);
        } else {
            $this->mRepo->createSolo($tournamentId, 'grand', 2, 1, $bestOf, null, null);
        }

        // Auto-advance byes in winners bracket only (no loser drop when opponent is null).
        for ($r = 1; $r <= $rounds; $r++) {
            $matchesInRound = (int)($bracketSize / (2 ** $r));
            for ($pos = 1; $pos <= $matchesInRound; $pos++) {
                [$a, $b] = $wParts[$r][$pos] ?? [null, null];
                $winner = null;

                if ($a !== null && $b === null) {
                    $winner = $a;
                } elseif ($a === null && $b !== null) {
                    $winner = $b;
                }

                if ($winner === null) {
                    continue;
                }

                $matchId = (int)($wIds[$r][$pos] ?? 0);
                if ($matchId <= 0) {
                    continue;
                }

                if ($participantType === 'team') {
                    $this->mRepo->confirmTeamWinner($matchId, $winner);
                } else {
                    $this->mRepo->confirmSoloWinner($matchId, $winner);
                }

                if ($r >= $rounds) {
                    // Winner bracket champion -> grand final slot 1.
                    if ($participantType === 'team') {
                        $this->mRepo->setTeamSlot($grandId, 1, $winner);
                    } else {
                        $this->mRepo->setSoloSlot($grandId, 1, $winner);
                    }
                    continue;
                }

                $nextRound = $r + 1;
                $nextPos = (int)(($pos + 1) / 2);
                $nextSlot = ($pos % 2 === 1) ? 1 : 2;
                $nextMatchId = (int)($wIds[$nextRound][$nextPos] ?? 0);

                if ($nextMatchId <= 0) {
                    continue;
                }

                $wParts[$nextRound][$nextPos] ??= [null, null];
                $wParts[$nextRound][$nextPos][$nextSlot - 1] = $winner;

                if ($participantType === 'team') {
                    $this->mRepo->setTeamSlot($nextMatchId, $nextSlot, $winner);
                } else {
                    $this->mRepo->setSoloSlot($nextMatchId, $nextSlot, $winner);
                }
            }
        }
    }
}
