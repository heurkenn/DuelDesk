<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var bool $isNew */
/** @var array<string, mixed>|null $event */
/** @var list<array<string, mixed>> $tournaments */
/** @var list<array<string, mixed>> $availableTournaments */
/** @var array{name:string,status:string,starts_at:string,ends_at:string,location:string,description:string} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */

function field_error_lan(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}

$id = is_array($event) ? (int)($event['id'] ?? 0) : 0;
$slug = is_array($event) ? (string)($event['slug'] ?? '') : '';

$action = $isNew ? '/admin/lan' : ('/admin/lan/' . $id);
$pageTitle = $isNew ? 'Nouveau LAN' : 'Edit LAN';
$publicPath = $slug !== '' ? ('/lan/' . $slug) : '';
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e($pageTitle) ?></h1>
        <p class="pagehead__lead">Un LAN regroupe plusieurs tournois sous un meme evenement.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin/lan">Retour</a>
        <?php if (!$isNew && $publicPath !== ''): ?>
            <a class="btn btn--ghost" href="<?= View::e($publicPath) ?>">Voir (public)</a>
        <?php endif; ?>
        <?php if (!$isNew): ?>
            <form method="post" action="/admin/lan/<?= (int)$id ?>/delete" class="inline" data-confirm="Supprimer ce LAN ? (les tournois seront detaches)">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--danger" type="submit">Supprimer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<form class="form" method="post" action="<?= View::e($action) ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Infos</h2>
                <p class="card__subtitle">Nom, statut, dates, lieu.</p>
            </div>
            <?php if (!$isNew && $publicPath !== ''): ?>
                <div class="pill pill--soft mono"><?= View::e($publicPath) ?></div>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <div class="form__grid">
                <label class="field field--full">
                    <span class="field__label">Nom</span>
                    <input class="input<?= field_error_lan($errors, 'name') ? ' input--error' : '' ?>" name="name" value="<?= View::e($old['name']) ?>" required maxlength="120">
                    <?php if (field_error_lan($errors, 'name')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'name')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Statut</span>
                    <select class="select<?= field_error_lan($errors, 'status') ? ' input--error' : '' ?>" name="status">
                        <?php foreach (['draft','published','running','completed'] as $st): ?>
                            <option value="<?= View::e($st) ?>" <?= $old['status'] === $st ? 'selected' : '' ?>><?= View::e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (field_error_lan($errors, 'status')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'status')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Debut (optionnel)</span>
                    <input class="input<?= field_error_lan($errors, 'starts_at') ? ' input--error' : '' ?>" type="datetime-local" name="starts_at" value="<?= View::e($old['starts_at']) ?>">
                    <span class="muted">UTC.</span>
                    <?php if (field_error_lan($errors, 'starts_at')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'starts_at')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Fin (optionnel)</span>
                    <input class="input<?= field_error_lan($errors, 'ends_at') ? ' input--error' : '' ?>" type="datetime-local" name="ends_at" value="<?= View::e($old['ends_at']) ?>">
                    <span class="muted">UTC.</span>
                    <?php if (field_error_lan($errors, 'ends_at')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'ends_at')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field field--full">
                    <span class="field__label">Lieu (optionnel)</span>
                    <input class="input<?= field_error_lan($errors, 'location') ? ' input--error' : '' ?>" name="location" value="<?= View::e($old['location']) ?>" maxlength="160" placeholder="Ex: Paris Expo - Hall 3">
                    <?php if (field_error_lan($errors, 'location')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'location')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field field--full">
                    <span class="field__label">Description (optionnel)</span>
                    <textarea class="textarea<?= field_error_lan($errors, 'description') ? ' input--error' : '' ?>" name="description" rows="4" maxlength="8000" placeholder="Infos, regles, horaires..."><?= View::e($old['description']) ?></textarea>
                    <?php if (field_error_lan($errors, 'description')): ?>
                        <span class="field__error"><?= View::e((string)field_error_lan($errors, 'description')) ?></span>
                    <?php endif; ?>
                </label>
            </div>
        </div>
        <div class="card__footer">
            <button class="btn btn--primary" type="submit">Enregistrer</button>
        </div>
    </section>
</form>

<?php if (!$isNew): ?>
    <section class="card" style="margin-top: 14px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Tournois dans ce LAN</h2>
                <p class="card__subtitle">Liste des tournois rattaches a cet evenement.</p>
            </div>
            <div class="pill pill--soft"><?= count($tournaments) ?> tournoi(s)</div>
        </div>
        <div class="card__body">
            <?php if ($tournaments === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Aucun tournoi</div>
                    <div class="empty__hint">Ajoute un tournoi ci-dessous.</div>
                </div>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Tournoi</th>
                                <th>Jeu</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tournaments as $t): ?>
                                <?php
                                    $tid = (int)($t['id'] ?? 0);
                                    $tslug = (string)($t['slug'] ?? '');
                                ?>
                                <tr>
                                    <td class="table__strong"><?= View::e((string)($t['name'] ?? 'Tournoi')) ?></td>
                                    <td>
                                        <?php if (!empty($t['game_image_path'])): ?>
                                            <img class="gameicon" src="<?= View::e((string)$t['game_image_path']) ?>" alt="" loading="lazy" width="20" height="20">
                                        <?php endif; ?>
                                        <?= View::e((string)($t['game'] ?? '')) ?>
                                    </td>
                                    <td><span class="pill pill--soft"><?= View::e((string)($t['status'] ?? 'draft')) ?></span></td>
                                    <td class="table__right">
                                        <?php if ($tslug !== ''): ?>
                                            <a class="link" href="/t/<?= View::e($tslug) ?>">Public</a>
                                            <span class="meta__dot" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <a class="link" href="/admin/tournaments/<?= (int)$tid ?>">Admin</a>
                                        <span class="meta__dot" aria-hidden="true"></span>
                                        <form method="post" action="/admin/lan/<?= (int)$id ?>/tournaments/<?= (int)$tid ?>/detach" class="inline" data-confirm="Retirer ce tournoi du LAN ?">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--ghost btn--compact" type="submit">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card__footer">
            <a class="btn btn--ghost" href="/tournaments/new?lan_event_id=<?= (int)$id ?>">Creer un tournoi</a>
        </div>
    </section>

    <section class="card" style="margin-top: 14px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Ajouter un tournoi existant</h2>
                <p class="card__subtitle">Uniquement les tournois qui ne sont dans aucun LAN.</p>
            </div>
        </div>
        <div class="card__body">
            <?php if ($availableTournaments === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Aucun tournoi disponible</div>
                    <div class="empty__hint">Tous les tournois sont deja rattaches a un LAN, ou il n'y en a pas.</div>
                </div>
            <?php else: ?>
                <form method="post" action="/admin/lan/<?= (int)$id ?>/tournaments/attach" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <select class="select" name="tournament_id" required>
                        <option value="" disabled selected>Choisir un tournoi...</option>
                        <?php foreach ($availableTournaments as $t): ?>
                            <?php $tid = (int)($t['id'] ?? 0); ?>
                            <option value="<?= (int)$tid ?>"><?= View::e((string)($t['name'] ?? ('#' . $tid))) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary" type="submit">Ajouter</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

