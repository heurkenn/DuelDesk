<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var list<array<string, mixed>> $tournaments */
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Tournois</h1>
        <p class="pagehead__lead">Liste des tournois suivis sur DuelDesk.</p>
    </div>
    <div>
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
                        <td class="table__right"><a class="link" href="/t/<?= View::e((string)($t['slug'] ?? '')) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
