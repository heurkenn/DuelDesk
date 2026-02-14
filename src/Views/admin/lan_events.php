<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var list<array<string, mixed>> $events */
/** @var string $csrfToken */
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">LAN</h1>
        <p class="pagehead__lead">Evenements qui contiennent plusieurs tournois.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin">Retour admin</a>
        <a class="btn btn--primary" href="/admin/lan/new">Nouveau LAN</a>
    </div>
</div>

<?php if ($events === []): ?>
    <div class="empty">
        <div class="empty__title">Aucun LAN</div>
        <div class="empty__hint">Cree un evenement, puis rattache des tournois.</div>
    </div>
<?php else: ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Debut</th>
                    <th>Lieu</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $e): ?>
                    <?php
                        $id = (int)($e['id'] ?? 0);
                        $slug = (string)($e['slug'] ?? '');
                        $startsAt = is_string($e['starts_at'] ?? null) ? (string)$e['starts_at'] : '';
                        $startsAtPretty = '';
                        if ($startsAt !== '') {
                            $v = substr($startsAt, 0, 16);
                            $startsAtPretty = ($v === false ? $startsAt : $v) . ' UTC';
                        }
                        $location = is_string($e['location'] ?? null) ? trim((string)$e['location']) : '';
                    ?>
                    <tr>
                        <td class="table__strong"><?= View::e((string)($e['name'] ?? 'LAN')) ?></td>
                        <?php
                            $ptype = (string)($e['participant_type'] ?? 'solo');
                            if (!in_array($ptype, ['solo', 'team'], true)) {
                                $ptype = 'solo';
                            }
                        ?>
                        <td><span class="pill"><?= View::e($ptype === 'team' ? 'Equipe' : 'Solo') ?></span></td>
                        <td><span class="pill pill--soft"><?= View::e((string)($e['status'] ?? 'draft')) ?></span></td>
                        <td><?= $startsAtPretty !== '' ? View::e($startsAtPretty) : '<span class="muted">-</span>' ?></td>
                        <td><?= $location !== '' ? View::e($location) : '<span class="muted">-</span>' ?></td>
                        <td class="table__right">
                            <?php if ($slug !== ''): ?>
                                <a class="link" href="/lan/<?= View::e($slug) ?>">Public</a>
                                <span class="meta__dot" aria-hidden="true"></span>
                            <?php endif; ?>
                            <a class="link" href="/admin/lan/<?= (int)$id ?>">Gerer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
