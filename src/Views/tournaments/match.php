<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $tournament */
/** @var array<string, mixed> $match */
/** @var 'solo'|'team' $participantType */

$tid = (int)($tournament['id'] ?? 0);
$mid = (int)($match['id'] ?? 0);
$bracket = (string)($match['bracket'] ?? 'winners');
$round = (int)($match['round'] ?? 0);
$pos = (int)($match['round_pos'] ?? 0);
$status = (string)($match['status'] ?? 'pending');
$bestOf = (int)($match['best_of'] ?? 0);

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

$tag = $bracket === 'grand' ? 'GF' : (($bracket === 'losers' ? 'L' : 'W') . $round . '#' . $pos);

$bracketLabel = match ($bracket) {
    'winners' => 'Gagnants',
    'losers' => 'Perdants',
    'grand' => 'Finale',
    default => $bracket,
};

$score1 = (int)($match['score1'] ?? 0);
$score2 = (int)($match['score2'] ?? 0);
$scoreText = $status === 'confirmed' ? ($score1 . ' - ' . $score2) : 'TBD';
$who = $participantType === 'team' ? 'Equipe' : 'Joueur';
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
