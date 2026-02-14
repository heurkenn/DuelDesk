<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\MatchRepository;

/**
 * Propagate winners/losers through generated brackets (SE/DE).
 *
 * This logic was originally implemented in AdminTournamentController.
 */
final class BracketAdvancer
{
    public function __construct(private readonly MatchRepository $mRepo = new MatchRepository())
    {
    }

    private function setSoloNextSlot(array $next, int $nextMatchId, int $slot, int $playerId): void
    {
        if (($next['status'] ?? 'pending') === 'confirmed') {
            throw new \RuntimeException('Conflit: le match suivant est deja confirme. Fais un reset bracket.');
        }

        $existing = $slot === 1 ? ($next['player1_id'] ?? null) : ($next['player2_id'] ?? null);
        if ($existing !== null && (int)$existing !== $playerId) {
            throw new \RuntimeException('Conflit: le match suivant est deja rempli. Fais un reset bracket.');
        }

        $this->mRepo->setSoloSlot($nextMatchId, $slot, $playerId);

        // Auto-advance BYE in losers bracket when the missing slot is guaranteed dead (came from a winners BYE).
        if (($next['bracket'] ?? null) === 'losers') {
            $tid = (int)($next['tournament_id'] ?? 0);
            $lr = (int)($next['round'] ?? 0);
            $pos = (int)($next['round_pos'] ?? 0);
            if ($tid > 0 && $lr > 0 && $pos > 0) {
                $otherSlot = $slot === 1 ? 2 : 1;
                $other = $otherSlot === 1 ? ($next['player1_id'] ?? null) : ($next['player2_id'] ?? null);
                if ($other === null && $this->losersSlotIsDeadFromWinnersBye($tid, $lr, $pos, $otherSlot, 'solo')) {
                    $this->mRepo->confirmSoloWinner($nextMatchId, $playerId);
                    $this->advanceSoloDoubleElim($tid, 'losers', $lr, $pos, $playerId, 0);
                }
            }
        }
    }

    private function setTeamNextSlot(array $next, int $nextMatchId, int $slot, int $teamId): void
    {
        if (($next['status'] ?? 'pending') === 'confirmed') {
            throw new \RuntimeException('Conflit: le match suivant est deja confirme. Fais un reset bracket.');
        }

        $existing = $slot === 1 ? ($next['team1_id'] ?? null) : ($next['team2_id'] ?? null);
        if ($existing !== null && (int)$existing !== $teamId) {
            throw new \RuntimeException('Conflit: le match suivant est deja rempli. Fais un reset bracket.');
        }

        $this->mRepo->setTeamSlot($nextMatchId, $slot, $teamId);

        // Auto-advance BYE in losers bracket when the missing slot is guaranteed dead (came from a winners BYE).
        if (($next['bracket'] ?? null) === 'losers') {
            $tid = (int)($next['tournament_id'] ?? 0);
            $lr = (int)($next['round'] ?? 0);
            $pos = (int)($next['round_pos'] ?? 0);
            if ($tid > 0 && $lr > 0 && $pos > 0) {
                $otherSlot = $slot === 1 ? 2 : 1;
                $other = $otherSlot === 1 ? ($next['team1_id'] ?? null) : ($next['team2_id'] ?? null);
                if ($other === null && $this->losersSlotIsDeadFromWinnersBye($tid, $lr, $pos, $otherSlot, 'team')) {
                    $this->mRepo->confirmTeamWinner($nextMatchId, $teamId);
                    $this->advanceTeamDoubleElim($tid, 'losers', $lr, $pos, $teamId, 0);
                }
            }
        }
    }

    private function losersSlotIsDeadFromWinnersBye(
        int $tournamentId,
        int $losersRound,
        int $losersPos,
        int $slot,
        string $participantType
    ): bool {
        // In our DE mapping, only these slots come directly from a winners "loser drop":
        // - L1#p slot1 <- loser of W1#(2p-1)
        // - L1#p slot2 <- loser of W1#(2p)
        // - L(2r-2)#p slot2 <- loser of Wr#p  (for r >= 2)
        $wRound = 0;
        $wPos = 0;

        if ($losersRound === 1) {
            $wRound = 1;
            $wPos = $slot === 1 ? ((2 * $losersPos) - 1) : (2 * $losersPos);
        } elseif (($losersRound % 2) === 0 && $slot === 2) {
            $wRound = intdiv($losersRound, 2) + 1;
            $wPos = $losersPos;
        } else {
            return false;
        }

        if ($wRound <= 0 || $wPos <= 0) {
            return false;
        }

        $w = $this->mRepo->findByTournamentKey($tournamentId, 'winners', $wRound, $wPos);
        if (!is_array($w)) {
            return false;
        }
        if (($w['status'] ?? 'pending') !== 'confirmed') {
            return false;
        }

        if ($participantType === 'team') {
            $a = $w['team1_id'] ?? null;
            $b = $w['team2_id'] ?? null;
        } else {
            $a = $w['player1_id'] ?? null;
            $b = $w['player2_id'] ?? null;
        }

        // Winners BYE match: exactly one side is present. No loser exists -> slot is dead.
        return ($a === null && $b !== null) || ($a !== null && $b === null);
    }

    public function advanceSoloWinner(int $tournamentId, string $bracket, int $round, int $roundPos, int $winnerPlayerId): void
    {
        $next = $this->mRepo->findByTournamentKey($tournamentId, $bracket, $round + 1, (int)(($roundPos + 1) / 2));
        if ($next === null) {
            return;
        }

        $nextMatchId = (int)($next['id'] ?? 0);
        if ($nextMatchId <= 0) {
            return;
        }

        $slot = ($roundPos % 2 === 1) ? 1 : 2;
        $this->setSoloNextSlot($next, $nextMatchId, $slot, $winnerPlayerId);
    }

    public function advanceTeamWinner(int $tournamentId, string $bracket, int $round, int $roundPos, int $winnerTeamId): void
    {
        $next = $this->mRepo->findByTournamentKey($tournamentId, $bracket, $round + 1, (int)(($roundPos + 1) / 2));
        if ($next === null) {
            return;
        }

        $nextMatchId = (int)($next['id'] ?? 0);
        if ($nextMatchId <= 0) {
            return;
        }

        $slot = ($roundPos % 2 === 1) ? 1 : 2;
        $this->setTeamNextSlot($next, $nextMatchId, $slot, $winnerTeamId);
    }

    public function advanceSoloDoubleElim(
        int $tournamentId,
        string $bracket,
        int $round,
        int $roundPos,
        int $winnerPlayerId,
        int $loserPlayerId
    ): void {
        $wRounds = $this->mRepo->maxRoundForBracket($tournamentId, 'winners');
        if ($wRounds <= 0) {
            return;
        }

        $lRounds = (2 * $wRounds) - 2;
        if ($lRounds <= 0) {
            return;
        }

        $grand1 = $this->mRepo->findByTournamentKey($tournamentId, 'grand', 1, 1);
        $grand1Id = is_array($grand1) ? (int)($grand1['id'] ?? 0) : 0;
        $grand2 = $this->mRepo->findByTournamentKey($tournamentId, 'grand', 2, 1);
        $grand2Id = is_array($grand2) ? (int)($grand2['id'] ?? 0) : 0;

        if ($bracket === 'grand') {
            // GF2 is only played if the losers bracket champion wins GF1.
            if ($round !== 1 || $grand2Id <= 0 || !is_array($grand2)) {
                return;
            }

            $wf = $this->mRepo->findByTournamentKey($tournamentId, 'winners', $wRounds, 1);
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
                $this->mRepo->voidMatch($grand2Id);
                return;
            }

            // Losers champ won GF1: activate GF2 with the same participants.
            $this->mRepo->resetSoloForReplay($grand2Id, $wChamp, $other);
            return;
        }

        if ($bracket === 'winners') {
            // Winner -> next winners match (or grand final slot 1).
            if ($round >= $wRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setSoloNextSlot($grand1, $grand1Id, 1, $winnerPlayerId);
                }
            } else {
                $next = $this->mRepo->findByTournamentKey($tournamentId, 'winners', $round + 1, (int)(($roundPos + 1) / 2));
                if (is_array($next)) {
                    $nextId = (int)($next['id'] ?? 0);
                    if ($nextId > 0) {
                        $slot = ($roundPos % 2 === 1) ? 1 : 2;
                        $this->setSoloNextSlot($next, $nextId, $slot, $winnerPlayerId);
                    }
                }
            }

            // Loser -> losers bracket drop.
            $dropRound = $round === 1 ? 1 : ((2 * $round) - 2);
            if ($dropRound > 0 && $dropRound <= $lRounds) {
                $dropPos = $round === 1 ? (int)(($roundPos + 1) / 2) : $roundPos;
                $dropSlot = $round === 1 ? (($roundPos % 2 === 1) ? 1 : 2) : 2;

                $drop = $this->mRepo->findByTournamentKey($tournamentId, 'losers', $dropRound, $dropPos);
                if (is_array($drop)) {
                    $dropId = (int)($drop['id'] ?? 0);
                    if ($dropId > 0) {
                        $this->setSoloNextSlot($drop, $dropId, $dropSlot, $loserPlayerId);
                    }
                }
            }

            return;
        }

        if ($bracket === 'losers') {
            // Winner -> next losers match (or grand final slot 2).
            if ($round >= $lRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setSoloNextSlot($grand1, $grand1Id, 2, $winnerPlayerId);
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

            $next = $this->mRepo->findByTournamentKey($tournamentId, 'losers', $nextRound, $nextPos);
            if (!is_array($next)) {
                return;
            }
            $nextId = (int)($next['id'] ?? 0);
            if ($nextId <= 0) {
                return;
            }

            $this->setSoloNextSlot($next, $nextId, $nextSlot, $winnerPlayerId);
        }
    }

    public function advanceTeamDoubleElim(
        int $tournamentId,
        string $bracket,
        int $round,
        int $roundPos,
        int $winnerTeamId,
        int $loserTeamId
    ): void {
        $wRounds = $this->mRepo->maxRoundForBracket($tournamentId, 'winners');
        if ($wRounds <= 0) {
            return;
        }

        $lRounds = (2 * $wRounds) - 2;
        if ($lRounds <= 0) {
            return;
        }

        $grand1 = $this->mRepo->findByTournamentKey($tournamentId, 'grand', 1, 1);
        $grand1Id = is_array($grand1) ? (int)($grand1['id'] ?? 0) : 0;
        $grand2 = $this->mRepo->findByTournamentKey($tournamentId, 'grand', 2, 1);
        $grand2Id = is_array($grand2) ? (int)($grand2['id'] ?? 0) : 0;

        if ($bracket === 'grand') {
            if ($round !== 1 || $grand2Id <= 0 || !is_array($grand2)) {
                return;
            }

            $wf = $this->mRepo->findByTournamentKey($tournamentId, 'winners', $wRounds, 1);
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
                $this->mRepo->voidMatch($grand2Id);
                return;
            }

            $this->mRepo->resetTeamForReplay($grand2Id, $wChamp, $other);
            return;
        }

        if ($bracket === 'winners') {
            if ($round >= $wRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setTeamNextSlot($grand1, $grand1Id, 1, $winnerTeamId);
                }
            } else {
                $next = $this->mRepo->findByTournamentKey($tournamentId, 'winners', $round + 1, (int)(($roundPos + 1) / 2));
                if (is_array($next)) {
                    $nextId = (int)($next['id'] ?? 0);
                    if ($nextId > 0) {
                        $slot = ($roundPos % 2 === 1) ? 1 : 2;
                        $this->setTeamNextSlot($next, $nextId, $slot, $winnerTeamId);
                    }
                }
            }

            $dropRound = $round === 1 ? 1 : ((2 * $round) - 2);
            if ($dropRound > 0 && $dropRound <= $lRounds) {
                $dropPos = $round === 1 ? (int)(($roundPos + 1) / 2) : $roundPos;
                $dropSlot = $round === 1 ? (($roundPos % 2 === 1) ? 1 : 2) : 2;

                $drop = $this->mRepo->findByTournamentKey($tournamentId, 'losers', $dropRound, $dropPos);
                if (is_array($drop)) {
                    $dropId = (int)($drop['id'] ?? 0);
                    if ($dropId > 0) {
                        $this->setTeamNextSlot($drop, $dropId, $dropSlot, $loserTeamId);
                    }
                }
            }

            return;
        }

        if ($bracket === 'losers') {
            if ($round >= $lRounds) {
                if ($grand1Id > 0 && is_array($grand1)) {
                    $this->setTeamNextSlot($grand1, $grand1Id, 2, $winnerTeamId);
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

            $next = $this->mRepo->findByTournamentKey($tournamentId, 'losers', $nextRound, $nextPos);
            if (!is_array($next)) {
                return;
            }
            $nextId = (int)($next['id'] ?? 0);
            if ($nextId <= 0) {
                return;
            }

            $this->setTeamNextSlot($next, $nextId, $nextSlot, $winnerTeamId);
        }
    }
}

