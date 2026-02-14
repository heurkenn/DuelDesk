<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var int $tid */
/** @var string $csrfToken */
/** @var string $format */
/** @var int $matchCount */
/** @var bool $canGenerateBracket */
/** @var list<string> $incompleteTeams */
/** @var 'solo'|'team' $participantType */
/** @var string $teamMatchMode */
/** @var list<array<string,mixed>> $matches */
?>

<section class="card tpanel" style="margin-top: 18px;">
    <div class="card__header">
        <div>
            <h2 class="card__title">Bracket</h2>
            <p class="card__subtitle">
                <?= (int)$matchCount ?> match(s)
                <span class="meta__dot" aria-hidden="true"></span>
                <span class="muted"><?= View::e($format) ?></span>
            </p>
        </div>
    </div>
    <div class="card__body">
        <?php if (!in_array($format, ['single_elim', 'double_elim', 'round_robin'], true)): ?>
            <div class="muted">Generation dispo uniquement pour <span class="mono">single_elim</span> / <span class="mono">double_elim</span> / <span class="mono">round_robin</span>.</div>
        <?php elseif ($matchCount > 0): ?>
            <div class="muted">Bracket deja genere.</div>
            <div style="margin-top: 12px;">
                <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/bracket/reset" class="inline" data-confirm="Reset le bracket ? (supprime tous les matchs)">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <button class="btn btn--danger btn--compact" type="submit">Reset bracket</button>
                </form>
            </div>
        <?php elseif ($participantType === 'team' && $incompleteTeams !== []): ?>
            <div class="muted">Equipes incompletes: <?= View::e(implode(', ', $incompleteTeams)) ?></div>
        <?php elseif (!$canGenerateBracket): ?>
            <div class="muted">Il faut au moins 2 participants pour generer.</div>
        <?php else: ?>
            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/bracket/generate" class="inline" data-confirm="Generer le bracket ?">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--primary" type="submit">Generer <?= $format === 'double_elim' ? 'double elim' : 'single elim' ?></button>
            </form>
            <div class="form__hint">Tip: fixe les seeds avant de generer (sinon ordre par inscription).</div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Matchs</h2>
        <div class="section__meta"><?= (int)$matchCount ?> match(s)</div>
    </div>

    <?php if ($matches === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun match</div>
            <div class="empty__hint">Genere le bracket pour creer les matchs.</div>
        </div>
    <?php else: ?>
        <div class="tablewrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>Bracket</th>
                        <th>Round</th>
                        <th>Match</th>
                        <th>BO</th>
                        <th><?= $participantType === 'team' ? 'Equipe A' : 'Joueur A' ?></th>
                        <th>Score</th>
                        <th><?= $participantType === 'team' ? 'Equipe B' : 'Joueur B' ?></th>
                        <th>Horaire (UTC)</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $m): ?>
                        <?php
                            $mid = (int)($m['id'] ?? 0);
                            $bracket = (string)($m['bracket'] ?? 'winners');
                            $round = (int)($m['round'] ?? 0);
                            $pos = (int)($m['round_pos'] ?? 0);
                            $st = (string)($m['status'] ?? 'pending');
                            $bestOf = (int)($m['best_of'] ?? 0);
                            if ($bestOf <= 0) {
                                $bestOf = 3;
                            }
                            $score1 = (int)($m['score1'] ?? 0);
                            $score2 = (int)($m['score2'] ?? 0);
                            $reportedScore1 = $m['reported_score1'] ?? null;
                            $reportedScore2 = $m['reported_score2'] ?? null;
                            $reportedWinnerSlot = $m['reported_winner_slot'] ?? null;
                            $reportedByUsername = (string)($m['reported_by_username'] ?? '');
                            $counterScore1 = $m['counter_reported_score1'] ?? null;
                            $counterScore2 = $m['counter_reported_score2'] ?? null;
                            $counterWinnerSlot = $m['counter_reported_winner_slot'] ?? null;
                            $counterByUsername = (string)($m['counter_reported_by_username'] ?? '');
                            $scheduledAt = $m['scheduled_at'] ?? null;
                            $scheduledValue = to_datetime_local_admin($scheduledAt);

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

                            $aLabel = $aId === null ? 'TBD' : ($aName !== '' ? $aName : '#');
                            $bLabel = $bId === null ? 'TBD' : ($bName !== '' ? $bName : '#');
                            $winnerSlot = ($win !== null && $aId !== null && $win === $aId) ? '1' : (($win !== null && $bId !== null && $win === $bId) ? '2' : '');

                            $modeDisablesConfirm = ($participantType === 'team') && in_array($teamMatchMode, ['lineup_duels', 'multi_round'], true);
                            $canReport = !$modeDisablesConfirm && ($st !== 'confirmed') && ($aId !== null) && ($bId !== null);

                            $hasReportA = $reportedScore1 !== null && $reportedScore2 !== null && $reportedWinnerSlot !== null;
                            $hasReportB = $counterScore1 !== null && $counterScore2 !== null && $counterWinnerSlot !== null;

                            $prefAScore1 = $hasReportA ? (int)$reportedScore1 : $score1;
                            $prefAScore2 = $hasReportA ? (int)$reportedScore2 : $score2;
                            $prefAWinnerSlot = $hasReportA ? (string)(int)$reportedWinnerSlot : $winnerSlot;

                            $prefBScore1 = $hasReportB ? (int)$counterScore1 : $score1;
                            $prefBScore2 = $hasReportB ? (int)$counterScore2 : $score2;
                            $prefBWinnerSlot = $hasReportB ? (string)(int)$counterWinnerSlot : $winnerSlot;

                            if ($st === 'disputed' && $hasReportA && $hasReportB) {
                                $scoreLabel = 'A: ' . (int)$reportedScore1 . '-' . (int)$reportedScore2 . ' / B: ' . (int)$counterScore1 . '-' . (int)$counterScore2;
                            } elseif ($st === 'reported' && $hasReportA) {
                                $scoreLabel = (int)$reportedScore1 . ' - ' . (int)$reportedScore2;
                            } else {
                                $scoreLabel = $score1 . ' - ' . $score2;
                            }
                        ?>
                        <tr>
                            <td class="mono"><?= View::e($bracket) ?></td>
                            <td class="mono"><?= (int)$round ?></td>
                            <td class="mono">#<?= (int)$pos ?></td>
                            <td class="mono">
                                <?php if ($st === 'confirmed' || $modeDisablesConfirm): ?>
                                    <span class="pill pill--soft">BO<?= (int)$bestOf ?></span>
                                <?php else: ?>
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/bestof" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <select class="select select--compact" name="best_of">
                                            <?php foreach ([1, 3, 5, 7, 9] as $bo): ?>
                                                <option value="<?= (int)$bo ?>" <?= (int)$bestOf === (int)$bo ? 'selected' : '' ?>>BO<?= (int)$bo ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn--ghost btn--compact" type="submit">OK</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $winnerSlot === '1' ? 'table__strong' : '' ?>"><?= View::e($aLabel) ?></td>
                            <td class="mono"><?= View::e($scoreLabel) ?></td>
                            <td class="<?= $winnerSlot === '2' ? 'table__strong' : '' ?>"><?= View::e($bLabel) ?></td>
                            <td class="mono">
                                <?php if ($st === 'confirmed'): ?>
                                    <?php if (is_string($scheduledAt) && $scheduledAt !== ''): ?>
                                        <?= View::e(substr($scheduledAt, 0, 16) . ' UTC') ?>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                <?php elseif ($modeDisablesConfirm): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/schedule" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <input class="input input--xs input--date" type="datetime-local" name="scheduled_at" value="<?= View::e($scheduledValue) ?>">
                                        <button class="btn btn--ghost btn--compact" type="submit">OK</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($st === 'confirmed'): ?>
                                    <span class="pill">confirmed</span>
                                <?php else: ?>
                                    <?php if ($st === 'disputed'): ?>
                                        <span class="pill">disputed</span>
                                        <?php if ($reportedByUsername !== '' || $counterByUsername !== ''): ?>
                                            <span class="muted" style="margin-left: 8px;">
                                                <?php if ($reportedByUsername !== ''): ?>A: <?= View::e($reportedByUsername) ?><?php endif; ?>
                                                <?php if ($counterByUsername !== ''): ?><?= $reportedByUsername !== '' ? ' · ' : '' ?>B: <?= View::e($counterByUsername) ?><?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($st === 'reported'): ?>
                                        <span class="pill">reported</span>
                                        <?php if ($reportedByUsername !== ''): ?>
                                            <span class="muted" style="margin-left: 8px;">par <?= View::e($reportedByUsername) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="pill pill--soft"><?= View::e($st) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="table__right">
                                <a class="link" href="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>">Ouvrir</a>

                                <?php if ($canReport): ?>
                                    <?php if ($st === 'disputed' && $hasReportA && $hasReportB): ?>
                                        <div style="margin-top: 6px;">
                                            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefAWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefAWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefAWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer A</button>
                                            </form>
                                        </div>
                                        <div style="margin-top: 6px;">
                                            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefBScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefBScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefBWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefBWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefBWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer B</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top: 6px;">
                                            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefAWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefAWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefAWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($st, ['reported', 'disputed'], true)): ?>
                                        <div style="margin-top: 6px;">
                                            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report/reject" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <button class="btn btn--ghost btn--compact" type="submit">Rejeter</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

