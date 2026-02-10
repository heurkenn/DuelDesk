<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var list<array<string, mixed>> $games */
/** @var array{name:string,game_id:string,format:string,participant_type:string,team_size:string,status:string,starts_at:string} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */

function field_error(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}
?>

<?php $hasGames = $games !== []; ?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Nouveau tournoi</h1>
        <p class="pagehead__lead">Cree la fiche, puis on ajoute les joueurs et les matchs.</p>
    </div>
</div>

<form class="card form" method="post" action="/tournaments" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="card__header">
        <h2 class="card__title">Infos</h2>
        <p class="card__subtitle">Nom, jeu, format, et date de debut.</p>
    </div>

    <div class="card__body">
        <div class="form__grid">
            <label class="field">
                <span class="field__label">Nom du tournoi</span>
                <input class="input<?= field_error($errors, 'name') ? ' input--error' : '' ?>" name="name" value="<?= View::e($old['name']) ?>" placeholder="Ex: DuelDesk Weekly #12" required maxlength="120">
                <?php if (field_error($errors, 'name')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'name')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Jeu</span>
                <?php if (!$hasGames): ?>
                    <div class="empty empty--compact">
                        <div class="empty__title">Aucun jeu</div>
                        <div class="empty__hint">Ajoute au moins 1 jeu dans <a class="link" href="/admin/games">Admin → Jeux</a>.</div>
                    </div>
                <?php else: ?>
                    <select class="select<?= field_error($errors, 'game_id') ? ' input--error' : '' ?>" name="game_id" required>
                        <?php foreach ($games as $g): ?>
                            <?php $gid = (string)($g['id'] ?? ''); ?>
                            <option value="<?= (int)$gid ?>" <?= $gid === (string)$old['game_id'] ? 'selected' : '' ?>>
                                <?= View::e((string)($g['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (field_error($errors, 'game_id')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'game_id')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Format</span>
                <select class="select<?= field_error($errors, 'format') ? ' input--error' : '' ?>" name="format">
                    <option value="single_elim" <?= $old['format'] === 'single_elim' ? 'selected' : '' ?>>Simple elimination</option>
                    <option value="double_elim" <?= $old['format'] === 'double_elim' ? 'selected' : '' ?>>Double elimination</option>
                    <option value="round_robin" <?= $old['format'] === 'round_robin' ? 'selected' : '' ?>>Round robin</option>
                </select>
                <?php if (field_error($errors, 'format')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'format')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Participants</span>
                <select class="select<?= field_error($errors, 'participant_type') ? ' input--error' : '' ?>" name="participant_type">
                    <option value="solo" <?= ($old['participant_type'] ?? 'solo') === 'solo' ? 'selected' : '' ?>>Solo (1v1)</option>
                    <option value="team" <?= ($old['participant_type'] ?? 'solo') === 'team' ? 'selected' : '' ?>>Equipe</option>
                </select>
                <?php if (field_error($errors, 'participant_type')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'participant_type')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Taille d'equipe</span>
                <input class="input<?= field_error($errors, 'team_size') ? ' input--error' : '' ?>" name="team_size" value="<?= View::e((string)($old['team_size'] ?? '2')) ?>" placeholder="Ex: 2 ou 5" inputmode="numeric">
                <span class="muted">Utilise uniquement si le tournoi est en mode equipe.</span>
                <?php if (field_error($errors, 'team_size')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'team_size')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Statut</span>
                <select class="select<?= field_error($errors, 'status') ? ' input--error' : '' ?>" name="status">
                    <option value="draft" <?= $old['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $old['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="running" <?= $old['status'] === 'running' ? 'selected' : '' ?>>Running</option>
                    <option value="completed" <?= $old['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
                <?php if (field_error($errors, 'status')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'status')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field field--full">
                <span class="field__label">Date de debut (optionnel)</span>
                <input class="input<?= field_error($errors, 'starts_at') ? ' input--error' : '' ?>" type="datetime-local" name="starts_at" value="<?= View::e($old['starts_at']) ?>">
                <?php if (field_error($errors, 'starts_at')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'starts_at')) ?></span>
                <?php endif; ?>
            </label>
        </div>
    </div>

    <div class="card__footer">
        <a class="btn btn--ghost" href="/tournaments">Annuler</a>
        <button class="btn btn--primary" type="submit" <?= !$hasGames ? 'disabled' : '' ?>>Creer</button>
    </div>
</form>
