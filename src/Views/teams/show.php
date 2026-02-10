<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string, mixed> $team */
/** @var array<string, mixed> $tournament */
/** @var list<array<string, mixed>> $members */
/** @var bool $isMember */
/** @var string $myRole */
/** @var string $joinCode */
/** @var bool $canManage */
/** @var bool $rosterLocked */
/** @var string $csrfToken */

$tid = (int)($tournament['id'] ?? 0);
$tSlug = (string)($tournament['slug'] ?? '');
$tPublicPath = $tSlug !== '' ? ('/t/' . $tSlug) : ('/tournaments/' . $tid);

$teamName = (string)($team['name'] ?? 'Equipe');
$count = count($members);
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e($teamName) ?></h1>
        <p class="pagehead__lead">
            <span class="pill">Equipe</span>
            <?php if ($myRole !== ''): ?>
                <span class="pill pill--soft"><?= View::e($myRole) ?></span>
            <?php endif; ?>
            <span class="meta__dot" aria-hidden="true"></span>
            Tournoi: <a class="link" href="<?= View::e($tPublicPath) ?>"><?= View::e((string)($tournament['name'] ?? '')) ?></a>
        </p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="<?= View::e($tPublicPath) ?>">Retour tournoi</a>
    </div>
</div>

<div class="split">
    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Membres</h2>
                <p class="card__subtitle">
                    <?= (int)$count ?> membre(s)
                    <?php if ($canManage): ?>
                        <span class="meta__dot" aria-hidden="true"></span>
                        <span class="pill pill--soft">gestion</span>
                        <?php if ($rosterLocked): ?>
                            <span class="pill pill--soft">roster verrouille</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="card__body">
            <?php if ($members === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Roster vide</div>
                    <div class="empty__hint">Cette equipe n'a plus de membres.</div>
                </div>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Role</th>
                                <th>Rejoint le</th>
                                <?php if ($canManage && !$rosterLocked): ?>
                                    <th class="table__right">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <?php
                                    $uid = (int)($m['user_id'] ?? 0);
                                    $role = (string)($m['role'] ?? 'member');
                                    $isCaptain = $role === 'captain';
                                ?>
                                <tr>
                                    <td class="table__strong"><?= View::e((string)($m['username'] ?? '')) ?></td>
                                    <td class="mono">
                                        <?= View::e($role) ?>
                                        <?php if ($isCaptain): ?><span class="pill pill--soft">c</span><?php endif; ?>
                                    </td>
                                    <td class="mono"><?= View::e((string)($m['joined_at'] ?? '')) ?></td>
                                    <?php if ($canManage && !$rosterLocked): ?>
                                        <td class="table__right">
                                            <?php if ($uid <= 0 || $isCaptain): ?>
                                                <span class="muted">-</span>
                                            <?php else: ?>
                                                <form method="post" action="/teams/<?= (int)($team['id'] ?? 0) ?>/members/<?= (int)$uid ?>/captain" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                    <button class="btn btn--ghost btn--compact" type="submit">Capitaine</button>
                                                </form>
                                                <form method="post" action="/teams/<?= (int)($team['id'] ?? 0) ?>/members/<?= (int)$uid ?>/kick" class="inline" data-confirm="Kick ce membre ?">
                                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                    <button class="btn btn--danger btn--compact" type="submit">Kick</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <div class="section" style="margin-top: 18px;">
                    <div class="section__header">
                        <h3 class="section__title">Renommer</h3>
                        <div class="section__meta">Capitaine ou admin.</div>
                    </div>

                    <form class="inline" method="post" action="/teams/<?= (int)($team['id'] ?? 0) ?>/rename" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input class="input" name="name" value="<?= View::e($teamName) ?>" required maxlength="80">
                        <button class="btn btn--primary" type="submit">OK</button>
                    </form>

                    <?php if ($rosterLocked): ?>
                        <div class="muted" style="margin-top: 10px;">
                            Roster verrouille: les actions de kick/transfer sont bloquees (inscriptions fermees ou bracket genere).
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Join code</h2>
                <p class="card__subtitle">Pour inviter un mate.</p>
            </div>
        </div>
        <div class="card__body">
            <?php if (!$isMember): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Code masque</div>
                    <div class="empty__hint">Le join code est visible uniquement pour les membres de l'equipe.</div>
                </div>
            <?php else: ?>
                <div class="codeblock" style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
                    <code class="mono"><?= View::e($joinCode !== '' ? $joinCode : '-') ?></code>
                    <?php if ($joinCode !== ''): ?>
                        <button class="btn btn--ghost btn--compact" type="button" data-copy="<?= View::e($joinCode) ?>">Copier</button>
                    <?php endif; ?>
                </div>
                <div class="muted" style="margin-top: 10px;">
                    Pour rejoindre: va sur la page du tournoi et utilise "Rejoindre" + ce code.
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
