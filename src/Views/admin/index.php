<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array{users:int,games:int,tournaments:int,my_tournaments:int} $stats */
/** @var list<array<string, mixed>> $myTournaments */
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Admin</h1>
        <p class="pagehead__lead">Gestion des tournois et des roles.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin/users">Utilisateurs</a>
        <a class="btn btn--ghost" href="/admin/games">Jeux</a>
        <a class="btn btn--ghost" href="/admin/lan">LAN</a>
        <a class="btn btn--ghost" href="/admin/rulesets">Rulesets</a>
        <a class="btn btn--primary" href="/tournaments/new">Nouveau tournoi</a>
    </div>
</div>

<section class="cards cards--stats">
    <div class="card">
        <div class="card__body">
            <div class="statline">
                <div class="statline__label">Utilisateurs</div>
                <div class="statline__value"><?= (int)$stats['users'] ?></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card__body">
            <div class="statline">
                <div class="statline__label">Jeux</div>
                <div class="statline__value"><?= (int)$stats['games'] ?></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card__body">
            <div class="statline">
                <div class="statline__label">Tournois (total)</div>
                <div class="statline__value"><?= (int)$stats['tournaments'] ?></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card__body">
            <div class="statline">
                <div class="statline__label">Mes tournois</div>
                <div class="statline__value"><?= (int)$stats['my_tournaments'] ?></div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Mes tournois</h2>
        <div class="section__meta">Cree par toi</div>
    </div>

    <?php if ($myTournaments === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun tournoi</div>
            <div class="empty__hint">Cree ton premier tournoi depuis l'admin.</div>
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($myTournaments as $t): ?>
                <a class="card card--link" href="/tournaments/<?= (int)$t['id'] ?>">
                    <div class="card__header">
                        <div class="card__title"><?= View::e((string)$t['name']) ?></div>
                        <div class="pill"><?= View::e((string)$t['format']) ?></div>
                    </div>
                    <div class="card__body">
                        <div class="meta">
                            <span class="meta__item">
                                <?php if (!empty($t['game_image_path'])): ?>
                                    <img class="gameicon" src="<?= View::e((string)$t['game_image_path']) ?>" alt="" loading="lazy" width="22" height="22">
                                <?php endif; ?>
                                Jeu: <?= View::e((string)$t['game']) ?>
                            </span>
                            <span class="meta__dot" aria-hidden="true"></span>
                            <span class="meta__item">Statut: <?= View::e((string)$t['status']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
