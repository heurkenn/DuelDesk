<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var list<array<string, mixed>> $tournaments */
/** @var string $query */
/** @var int $page */
/** @var int $pages */
/** @var int $total */

function tournaments_page_link(int $page, string $query): string
{
    $params = ['page' => max(1, $page)];
    $q = trim($query);
    if ($q !== '') {
        $params['q'] = $q;
    }

    return '/tournaments?' . http_build_query($params);
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Tournois</h1>
        <p class="pagehead__lead">Liste des tournois suivis sur DuelDesk.</p>
    </div>
    <div class="pagehead__actions">
        <form method="get" action="/tournaments" class="inline">
            <input class="input input--compact" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Rechercher..." maxlength="120">
            <button class="btn btn--ghost btn--compact" type="submit">OK</button>
            <?php if (trim($query) !== ''): ?>
                <a class="btn btn--ghost btn--compact" href="/tournaments">Reset</a>
            <?php endif; ?>
        </form>
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn--primary" href="/tournaments/new">Nouveau</a>
        <?php else: ?>
            <a class="btn btn--primary" href="/login?redirect=<?= View::e(urlencode('/tournaments')) ?>">Connexion</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($tournaments === []): ?>
    <div class="empty">
        <div class="empty__title">Aucun tournoi</div>
        <div class="empty__hint">Seuls les admins peuvent creer des tournois.</div>
    </div>
<?php else: ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Jeu</th>
                    <th>Format</th>
                    <th>Statut</th>
                    <th>Debut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tournaments as $t): ?>
                    <tr>
                        <td class="table__strong"><?= View::e((string)$t['name']) ?></td>
                        <td>
                            <?php if (!empty($t['game_image_path'])): ?>
                                <img class="gameicon" src="<?= View::e((string)$t['game_image_path']) ?>" alt="" loading="lazy" width="24" height="24">
                            <?php endif; ?>
                            <span><?= View::e((string)$t['game']) ?></span>
                        </td>
                        <td><span class="pill"><?= View::e((string)$t['format']) ?></span></td>
                        <td><span class="pill pill--soft"><?= View::e((string)$t['status']) ?></span></td>
                        <td><?= $t['starts_at'] ? View::e((string)$t['starts_at']) : '<span class="muted">-</span>' ?></td>
                        <td class="table__right">
                            <?php $id = (int)($t['id'] ?? 0); $slug = (string)($t['slug'] ?? ''); ?>
                            <?php if (Auth::isAdmin() && $id > 0): ?>
                                <a class="link" href="/tournaments/<?= (int)$id ?>">Ouvrir</a>
                                <?php if ($slug !== ''): ?>
                                    <span class="meta__dot" aria-hidden="true"></span>
                                    <a class="link" href="/t/<?= View::e($slug) ?>">Public</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php $href = $slug !== '' ? ('/t/' . $slug) : ($id > 0 ? ('/tournaments/' . $id) : '#'); ?>
                                <a class="link" href="<?= View::e($href) ?>">Ouvrir</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="inline" style="margin-top: 12px; justify-content: space-between; width: 100%;">
            <span class="muted">Page <?= (int)$page ?> / <?= (int)$pages ?> (<?= (int)$total ?> total)</span>
            <div class="inline">
                <?php if ($page > 1): ?>
                    <a class="btn btn--ghost btn--compact" href="<?= View::e(tournaments_page_link($page - 1, $query)) ?>">Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn--ghost btn--compact" href="<?= View::e(tournaments_page_link($page + 1, $query)) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
