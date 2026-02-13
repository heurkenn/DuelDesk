<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $tournaments */

$eid = (int)($event['id'] ?? 0);
$slug = (string)($event['slug'] ?? '');
$status = (string)($event['status'] ?? 'draft');

$startsAt = is_string($event['starts_at'] ?? null) ? (string)$event['starts_at'] : '';
$endsAt = is_string($event['ends_at'] ?? null) ? (string)$event['ends_at'] : '';
$location = is_string($event['location'] ?? null) ? trim((string)$event['location']) : '';
$desc = is_string($event['description'] ?? null) ? trim((string)$event['description']) : '';

$fmt = static function (string $v): string {
    if ($v === '') {
        return '';
    }
    $s = substr($v, 0, 16);
    return ($s === false ? $v : $s) . ' UTC';
};
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e((string)($event['name'] ?? 'LAN')) ?></h1>
        <p class="pagehead__lead">
            <span class="pill pill--soft"><?= View::e($status) ?></span>
            <?php if ($startsAt !== ''): ?>
                <span class="meta__dot" aria-hidden="true"></span>
                <span class="pill pill--soft"><?= View::e($fmt($startsAt)) ?></span>
            <?php endif; ?>
            <?php if ($location !== ''): ?>
                <span class="meta__dot" aria-hidden="true"></span>
                <span class="pill pill--soft"><?= View::e($location) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagehead__actions">
        <?php if (Auth::isAdmin() && $eid > 0): ?>
            <a class="btn btn--ghost" href="/admin/lan/<?= (int)$eid ?>">Gerer</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="/lan">Retour</a>
    </div>
</div>

<?php if ($desc !== ''): ?>
    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Description</h2>
        </div>
        <div class="card__body">
            <div class="prose"><?= nl2br(View::e($desc)) ?></div>
        </div>
    </section>
<?php endif; ?>

<section class="section" style="margin-top: 16px;">
    <div class="section__header">
        <h2 class="section__title">Tournois</h2>
        <div class="section__meta"><?= count($tournaments) ?> tournoi(s)</div>
    </div>

    <?php if ($tournaments === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun tournoi</div>
            <div class="empty__hint">Cet evenement ne contient pas encore de tournois.</div>
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($tournaments as $t): ?>
                <?php
                    $tslug = (string)($t['slug'] ?? '');
                    $href = $tslug !== '' ? ('/t/' . $tslug) : ('/tournaments/' . (int)($t['id'] ?? 0));
                    $tStatus = (string)($t['status'] ?? 'draft');
                ?>
                <a class="card card--link" href="<?= View::e($href) ?>">
                    <div class="card__header">
                        <div class="card__title"><?= View::e((string)($t['name'] ?? 'Tournoi')) ?></div>
                        <div class="pill pill--soft"><?= View::e($tStatus) ?></div>
                    </div>
                    <div class="card__body">
                        <div class="meta">
                            <span class="meta__item">
                                <?php if (!empty($t['game_image_path'])): ?>
                                    <img class="gameicon" src="<?= View::e((string)$t['game_image_path']) ?>" alt="" loading="lazy" width="22" height="22">
                                <?php endif; ?>
                                Jeu: <?= View::e((string)($t['game'] ?? '')) ?>
                            </span>
                            <span class="meta__dot" aria-hidden="true"></span>
                            <span class="meta__item"><?= View::e((string)($t['format'] ?? '')) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($endsAt !== ''): ?>
    <div class="muted" style="margin-top: 14px;">Fin: <?= View::e($fmt($endsAt)) ?></div>
<?php endif; ?>

