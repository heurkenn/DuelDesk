<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var list<array<string, mixed>> $events */
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">LAN</h1>
        <p class="pagehead__lead">Evenements qui regroupent plusieurs tournois.</p>
    </div>
    <div class="pagehead__actions">
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn--primary" href="/admin/lan/new">Nouveau LAN</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($events === []): ?>
    <div class="empty">
        <div class="empty__title">Aucun LAN</div>
        <div class="empty__hint">Cree un evenement LAN pour regrouper plusieurs tournois.</div>
    </div>
<?php else: ?>
    <div class="cards">
        <?php foreach ($events as $e): ?>
            <?php
                $slug = (string)($e['slug'] ?? '');
                $href = $slug !== '' ? ('/lan/' . $slug) : '#';
                $status = (string)($e['status'] ?? 'draft');
                $ptype = (string)($e['participant_type'] ?? 'solo');
                if (!in_array($ptype, ['solo', 'team'], true)) {
                    $ptype = 'solo';
                }
                $startsAt = is_string($e['starts_at'] ?? null) ? (string)$e['starts_at'] : '';
                $startsAtPretty = '';
                if ($startsAt !== '') {
                    $v = substr($startsAt, 0, 16);
                    $startsAtPretty = ($v === false ? $startsAt : $v) . ' UTC';
                }
                $location = is_string($e['location'] ?? null) ? trim((string)$e['location']) : '';
            ?>
            <a class="card card--link" href="<?= View::e($href) ?>">
                <div class="card__header">
                    <div class="card__title"><?= View::e((string)($e['name'] ?? 'LAN')) ?></div>
                    <div class="pill pill--soft"><?= View::e($status) ?></div>
                </div>
                <div class="card__body">
                    <div class="meta">
                        <span class="meta__item">Type: <?= View::e($ptype === 'team' ? 'Equipe' : 'Solo') ?></span>
                        <span class="meta__dot" aria-hidden="true"></span>
                        <?php if ($startsAtPretty !== ''): ?>
                            <span class="meta__item">Debut: <?= View::e($startsAtPretty) ?></span>
                        <?php else: ?>
                            <span class="meta__item"><span class="muted">Debut: -</span></span>
                        <?php endif; ?>
                        <?php if ($location !== ''): ?>
                            <span class="meta__dot" aria-hidden="true"></span>
                            <span class="meta__item">Lieu: <?= View::e($location) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
