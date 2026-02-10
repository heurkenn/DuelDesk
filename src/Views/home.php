<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var list<array<string, mixed>> $recentTournaments */
/** @var string|null $dbError */
?>

<section class="hero">
    <div class="hero__copy">
        <h1 class="hero__title">Le tableau de bord de vos tournois.</h1>
        <p class="hero__lead">
            DuelDesk vous aide a organiser, suivre et publier des brackets propres, avec un style pro.
        </p>
        <div class="hero__actions">
            <?php if (Auth::isAdmin()): ?>
                <a class="btn btn--primary" href="/tournaments/new">Creer un tournoi</a>
                <a class="btn btn--ghost" href="/admin">Ouvrir l'admin</a>
            <?php else: ?>
                <a class="btn btn--primary" href="/tournaments">Voir les tournois</a>
                <a class="btn btn--ghost" href="/login">Connexion</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero__panel">
        <div class="stat">
            <div class="stat__label">Mode</div>
            <div class="stat__value">Prototype</div>
        </div>
        <div class="stat">
            <div class="stat__label">Stack</div>
            <div class="stat__value">PHP + JS + SQL</div>
        </div>
        <div class="stat">
            <div class="stat__label">Nom</div>
            <div class="stat__value">DuelDesk</div>
        </div>
    </div>
</section>

<?php if ($dbError !== null): ?>
    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Base de donnees non initialisee</h2>
            <p class="card__subtitle">Il manque probablement la migration SQL.</p>
        </div>
        <div class="card__body">
            <div class="codeblock">
                <code>docker compose up -d --build</code><br>
                <code>docker compose exec php php bin/migrate.php</code>
            </div>
            <p class="muted">Details (dev): <?= View::e($dbError) ?></p>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Tournois recents</h2>
        <div class="section__meta">Dernieres creations</div>
    </div>

    <?php if ($recentTournaments === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun tournoi pour le moment</div>
            <div class="empty__hint">Cree ton premier tournoi et commence a enregistrer des matchs.</div>
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($recentTournaments as $t): ?>
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
