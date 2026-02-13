<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $tournament */
/** @var array<string, mixed> $match */
/** @var 'solo'|'team' $participantType */
/** @var string $csrfToken */
/** @var bool $canReport */

$tid = (int)($tournament['id'] ?? 0);
$mid = (int)($match['id'] ?? 0);
$bracket = (string)($match['bracket'] ?? 'winners');
$round = (int)($match['round'] ?? 0);
$pos = (int)($match['round_pos'] ?? 0);
$status = (string)($match['status'] ?? 'pending');
$bestOf = (int)($match['best_of'] ?? 0);
$scheduledAt = is_string($match['scheduled_at'] ?? null) ? (string)$match['scheduled_at'] : '';

$reportedScore1 = $match['reported_score1'] ?? null;
$reportedScore2 = $match['reported_score2'] ?? null;
$reportedWinnerSlot = $match['reported_winner_slot'] ?? null;
$reportedAt = is_string($match['reported_at'] ?? null) ? (string)$match['reported_at'] : '';
$reportedByUsername = (string)($match['reported_by_username'] ?? '');

$counterScore1 = $match['counter_reported_score1'] ?? null;
$counterScore2 = $match['counter_reported_score2'] ?? null;
$counterWinnerSlot = $match['counter_reported_winner_slot'] ?? null;
$counterAt = is_string($match['counter_reported_at'] ?? null) ? (string)$match['counter_reported_at'] : '';
$counterByUsername = (string)($match['counter_reported_by_username'] ?? '');

if ($participantType === 'team') {
    $aId = $match['team1_id'] !== null ? (int)$match['team1_id'] : null;
    $bId = $match['team2_id'] !== null ? (int)$match['team2_id'] : null;
    $aName = (string)($match['t1_name'] ?? '');
    $bName = (string)($match['t2_name'] ?? '');
    $win = $match['winner_team_id'] !== null ? (int)$match['winner_team_id'] : null;
} else {
    $aId = $match['player1_id'] !== null ? (int)$match['player1_id'] : null;
    $bId = $match['player2_id'] !== null ? (int)$match['player2_id'] : null;
    $aName = (string)($match['p1_name'] ?? '');
    $bName = (string)($match['p2_name'] ?? '');
    $win = $match['winner_id'] !== null ? (int)$match['winner_id'] : null;
}

$aLabel = $aId === null
    ? (($bId !== null && $win !== null && $win === $bId) ? 'BYE' : 'TBD')
    : ($aName !== '' ? $aName : '#');
$bLabel = $bId === null
    ? (($aId !== null && $win !== null && $win === $aId) ? 'BYE' : 'TBD')
    : ($bName !== '' ? $bName : '#');

$aWin = $win !== null && $aId !== null && $win === $aId;
$bWin = $win !== null && $bId !== null && $win === $bId;

$tag = $bracket === 'grand'
    ? ($round >= 2 ? 'GF2' : 'GF')
    : ($bracket === 'round_robin'
        ? ('RR' . $round . '#' . $pos)
        : (($bracket === 'losers' ? 'L' : 'W') . $round . '#' . $pos)
    );

$bracketLabel = match ($bracket) {
    'winners' => 'Gagnants',
    'losers' => 'Perdants',
    'grand' => $round >= 2 ? 'Finale (Reset)' : 'Finale',
    'round_robin' => 'Round robin',
    default => $bracket,
};

$score1 = (int)($match['score1'] ?? 0);
$score2 = (int)($match['score2'] ?? 0);
$scoreText = $status === 'confirmed' ? ($score1 . ' - ' . $score2) : 'TBD';
$who = $participantType === 'team' ? 'Equipe' : 'Joueur';

$hasReportA = in_array($status, ['reported', 'disputed'], true)
    && $reportedScore1 !== null
    && $reportedScore2 !== null
    && $reportedWinnerSlot !== null;

$hasReportB = $status === 'disputed'
    && $counterScore1 !== null
    && $counterScore2 !== null
    && $counterWinnerSlot !== null;

$me = Auth::user();
$meUsername = is_array($me) ? (string)($me['username'] ?? '') : '';
$meIsA = ($meUsername !== '' && $reportedByUsername !== '' && $meUsername === $reportedByUsername);
$meIsB = ($meUsername !== '' && $counterByUsername !== '' && $meUsername === $counterByUsername);

// Prefill: your own report if available, otherwise show existing report A (useful as a starting point).
$reportScore1 = $score1;
$reportScore2 = $score2;
$reportWinnerSlot = '';

if ($meIsB && $hasReportB) {
    $reportScore1 = (int)$counterScore1;
    $reportScore2 = (int)$counterScore2;
    $reportWinnerSlot = (string)(int)$counterWinnerSlot;
} elseif ($meIsA && $hasReportA) {
    $reportScore1 = (int)$reportedScore1;
    $reportScore2 = (int)$reportedScore2;
    $reportWinnerSlot = (string)(int)$reportedWinnerSlot;
} elseif ($hasReportA) {
    $reportScore1 = (int)$reportedScore1;
    $reportScore2 = (int)$reportedScore2;
    $reportWinnerSlot = (string)(int)$reportedWinnerSlot;
}

$matchComplete = $aId !== null && $bId !== null;
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Match <?= View::e($tag) ?></h1>
        <p class="pagehead__lead">
            <span class="table__strong"><?= View::e((string)($tournament['name'] ?? '')) ?></span>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill"><?= View::e($bracketLabel) ?></span>
            <span class="pill pill--soft"><?= View::e($status) ?></span>
            <?php if ($bestOf > 0): ?>
                <span class="pill pill--soft"><?= 'BO' . (int)$bestOf ?></span>
            <?php endif; ?>
            <?php if ($scheduledAt !== ''): ?>
                <span class="pill pill--soft"><?= View::e(substr($scheduledAt, 0, 16) . ' UTC') ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagehead__actions">
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn--ghost" href="/admin/tournaments/<?= (int)$tid ?>">Admin</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="/tournaments/<?= (int)$tid ?>">Retour</a>
    </div>
</div>

<section class="card">
    <div class="card__header">
        <div>
            <h2 class="card__title"><?= View::e($who) ?> A vs <?= View::e($who) ?> B</h2>
            <p class="card__subtitle"><?= View::e($bracketLabel) ?> · Tour <?= (int)$round ?> · Match #<?= (int)$pos ?></p>
        </div>
        <div class="pill<?= $status === 'confirmed' ? '' : ' pill--soft' ?>"><?= View::e($scoreText) ?></div>
    </div>
    <div class="card__body">
        <div class="split">
            <div class="card card--nested">
                <div class="card__header">
                    <h3 class="card__title"><?= View::e($who) ?> A</h3>
                    <?php if ($aWin): ?><span class="pill">WIN</span><?php endif; ?>
                </div>
                <div class="card__body">
                    <div class="table__strong"><?= View::e($aLabel) ?></div>
                    <div class="muted mono" style="margin-top: 6px;">score: <?= (int)$score1 ?></div>
                </div>
            </div>

            <div class="card card--nested">
                <div class="card__header">
                    <h3 class="card__title"><?= View::e($who) ?> B</h3>
                    <?php if ($bWin): ?><span class="pill">WIN</span><?php endif; ?>
                </div>
                <div class="card__body">
                    <div class="table__strong"><?= View::e($bLabel) ?></div>
                    <div class="muted mono" style="margin-top: 6px;">score: <?= (int)$score2 ?></div>
                </div>
            </div>
        </div>

        <div class="section" style="margin-top: 18px;">
            <div class="codeblock"><code>ID match: <?= (int)$mid ?></code></div>
        </div>
    </div>
</section>

<?php if ($status === 'reported' && $hasReportA): ?>
    <section class="card" style="margin-top: 16px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Score reporte</h2>
                <p class="card__subtitle">En attente de validation admin</p>
            </div>
            <div class="pill">reported</div>
        </div>
        <div class="card__body">
            <div class="split" style="align-items: flex-start;">
                <div>
                    <div class="table__strong"><?= View::e((string)$reportScore1) ?> - <?= View::e((string)$reportScore2) ?></div>
                    <div class="muted" style="margin-top: 6px;">
                        Winner: <?= $reportWinnerSlot === '1' ? 'A' : 'B' ?>
                        <?php if ($reportedByUsername !== ''): ?>
                            <span class="meta__dot" aria-hidden="true"></span>
                            par <?= View::e($reportedByUsername) ?>
                        <?php endif; ?>
                        <?php if ($reportedAt !== ''): ?>
                            <span class="meta__dot" aria-hidden="true"></span>
                            <?= View::e(substr($reportedAt, 0, 16) . ' UTC') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="muted">
                    L'admin doit confirmer ce score dans le dashboard.
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($status === 'disputed' && $hasReportA && $hasReportB): ?>
    <section class="card" style="margin-top: 16px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Litige sur le score</h2>
                <p class="card__subtitle">Deux reports differents ont ete soumis (validation admin requise)</p>
            </div>
            <div class="pill">disputed</div>
        </div>
        <div class="card__body">
            <div class="split">
                <div class="card card--nested">
                    <div class="card__header">
                        <h3 class="card__title">Report A</h3>
                        <?php if ($reportedByUsername !== ''): ?><span class="pill pill--soft"><?= View::e($reportedByUsername) ?></span><?php endif; ?>
                    </div>
                    <div class="card__body">
                        <div class="table__strong"><?= (int)$reportedScore1 ?> - <?= (int)$reportedScore2 ?></div>
                        <div class="muted" style="margin-top: 6px;">
                            Winner: <?= ((string)(int)$reportedWinnerSlot) === '1' ? 'A' : 'B' ?>
                            <?php if ($reportedAt !== ''): ?>
                                <span class="meta__dot" aria-hidden="true"></span>
                                <?= View::e(substr($reportedAt, 0, 16) . ' UTC') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card card--nested">
                    <div class="card__header">
                        <h3 class="card__title">Report B</h3>
                        <?php if ($counterByUsername !== ''): ?><span class="pill pill--soft"><?= View::e($counterByUsername) ?></span><?php endif; ?>
                    </div>
                    <div class="card__body">
                        <div class="table__strong"><?= (int)$counterScore1 ?> - <?= (int)$counterScore2 ?></div>
                        <div class="muted" style="margin-top: 6px;">
                            Winner: <?= ((string)(int)$counterWinnerSlot) === '1' ? 'A' : 'B' ?>
                            <?php if ($counterAt !== ''): ?>
                                <span class="meta__dot" aria-hidden="true"></span>
                                <?= View::e(substr($counterAt, 0, 16) . ' UTC') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="muted" style="margin-top: 12px;">
                L'admin doit confirmer la bonne version dans le dashboard.
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($status !== 'confirmed' && $status !== 'void'): ?>
    <section class="card" style="margin-top: 16px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Reporter le score</h2>
                <p class="card__subtitle">
                    <?php if ($bestOf > 0): ?>BO<?= (int)$bestOf ?><?php else: ?>Best-of<?php endif; ?> · validation admin requise
                </p>
            </div>
            <?php if (!$canReport): ?>
                <div class="pill pill--soft">restreint</div>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if (!$matchComplete): ?>
                <div class="empty__hint">Ce match n'est pas encore defini (TBD).</div>
            <?php elseif (!$canReport): ?>
                <div class="empty__hint">
                    Seuls les joueurs (solo) ou les capitaines (team) peuvent reporter ce match.
                </div>
            <?php else: ?>
                <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report" class="split" style="gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div>
                        <label class="field__label" for="score1">Score A</label>
                        <input class="input" id="score1" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$reportScore1 ?>" required>
                    </div>
                    <div>
                        <label class="field__label" for="score2">Score B</label>
                        <input class="input" id="score2" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$reportScore2 ?>" required>
                    </div>
                    <div>
                        <label class="field__label" for="winner_slot">Winner</label>
                        <select class="select" id="winner_slot" name="winner_slot" required>
                            <option value="" <?= $reportWinnerSlot === '' ? 'selected' : '' ?>>Choisir...</option>
                            <option value="1" <?= $reportWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                            <option value="2" <?= $reportWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                        </select>
                    </div>
                    <div>
                        <button class="btn btn--primary" type="submit">Reporter</button>
                    </div>
                </form>
                <div class="muted" style="margin-top: 10px;">
                    Astuce: si c'est un score BO (ex BO3), indique les manches gagnees (ex: 2-1) pas les rounds.
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
