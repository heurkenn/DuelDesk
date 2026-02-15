<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $tournaments */
/** @var array{computedTournaments:int,totalTournaments:int,leaderboard:list<array{key:string,name:string,points:int,members:list<string>}>} $scoring */
/** @var array<string,mixed>|null $me */
/** @var bool $isRegistered */
/** @var array<string,mixed>|null $myLanTeam */
/** @var list<array<string,mixed>> $myLanTeamMembers */
/** @var int|null $teamSizeLimit */
/** @var string $csrfToken */

$eid = (int)($event['id'] ?? 0);
$slug = (string)($event['slug'] ?? '');
$status = (string)($event['status'] ?? 'draft');
$ptype = (string)($event['participant_type'] ?? 'solo');
if (!in_array($ptype, ['solo', 'team'], true)) {
    $ptype = 'solo';
}

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

$isOpen = in_array($status, ['published', 'running'], true);
$isAuthed = Auth::check();
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e((string)($event['name'] ?? 'LAN')) ?></h1>
        <p class="pagehead__lead">
            <span class="pill pill--soft"><?= View::e($status) ?></span>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill pill--soft"><?= View::e($ptype === 'team' ? 'Equipe' : 'Solo') ?></span>
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

<section class="card">
    <div class="card__header">
        <div>
            <h2 class="card__title">Inscription</h2>
            <p class="card__subtitle">Rejoindre ce LAN t'inscrit automatiquement a tous les tournois inclus.</p>
        </div>
        <div class="inline">
            <?php if (!$isAuthed): ?>
                <a class="btn btn--primary" href="/login?redirect=<?= View::e(urlencode('/lan/' . $slug)) ?>">Se connecter</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card__body">
        <?php if (!$isAuthed): ?>
            <div class="empty empty--compact">
                <div class="empty__title">Connexion requise</div>
                <div class="empty__hint">Connecte-toi pour t'inscrire.</div>
            </div>
        <?php else: ?>
            <?php if (!$isOpen && !Auth::isAdmin()): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Inscriptions fermees</div>
                    <div class="empty__hint">Statut: <?= View::e($status) ?>.</div>
                </div>
            <?php else: ?>
                <?php if ($ptype === 'solo'): ?>
                    <div class="inline" style="gap: 10px;">
                        <?php if (!empty($isRegistered)): ?>
                            <span class="pill pill--soft">inscrit</span>
                            <form method="post" action="/lan/<?= View::e($slug) ?>/withdraw" class="inline" data-confirm="Te desinscrire du LAN ? (retrait de tous les tournois)">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button class="btn btn--ghost" type="submit">Se desinscrire</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/lan/<?= View::e($slug) ?>/signup" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button class="btn btn--primary" type="submit">S'inscrire au LAN</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="muted" style="margin-top: 10px;">
                        Note: une fois inscrit, tu apparais dans les listes d'inscrits de chaque tournoi du LAN.
                    </div>
                <?php else: ?>
                    <?php if (is_array($myLanTeam) && (int)($myLanTeam['id'] ?? 0) > 0): ?>
                        <?php
                            $lanTeamId = (int)($myLanTeam['id'] ?? 0);
                            $joinCode = (string)($myLanTeam['join_code'] ?? '');
                        ?>
                        <div class="empty empty--compact">
                            <div class="empty__title">Ton equipe: <?= View::e((string)($myLanTeam['name'] ?? 'Equipe')) ?></div>
                            <?php if ($joinCode !== ''): ?>
                                <div class="empty__hint">Code: <span class="mono"><?= View::e($joinCode) ?></span></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($myLanTeamMembers !== []): ?>
                            <div class="tablewrap" style="margin-top: 12px;">
                                <table class="table table--compact">
                                    <thead>
                                        <tr>
                                            <th>Membre</th>
                                            <th>Role</th>
                                            <th>Depuis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myLanTeamMembers as $m): ?>
                                            <tr>
                                                <td class="table__strong"><?= View::e((string)($m['username'] ?? '')) ?></td>
                                                <td><span class="pill pill--soft"><?= View::e((string)($m['role'] ?? 'member')) ?></span></td>
                                                <td class="mono"><?= View::e((string)($m['joined_at'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="inline" style="margin-top: 12px;">
                            <form method="post" action="/lan/<?= View::e($slug) ?>/teams/<?= (int)$lanTeamId ?>/leave" class="inline" data-confirm="Quitter ton equipe LAN ?">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button class="btn btn--ghost" type="submit">Quitter l'equipe</button>
                            </form>
                        </div>

                        <?php if (is_int($teamSizeLimit) && $teamSizeLimit > 0): ?>
                            <div class="muted" style="margin-top: 10px;">
                                Limite roster (LAN): max <?= (int)$teamSizeLimit ?> (basee sur le plus petit team_size des tournois du LAN).
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $wrapClass = 'split'; ?>
                        <div class="<?= View::e($wrapClass) ?>">
                            <form class="card card--nested form" method="post" action="/lan/<?= View::e($slug) ?>/teams/create" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div class="card__header">
                                    <h3 class="card__title">Creer une equipe</h3>
                                    <p class="card__subtitle">Tu deviens capitaine.</p>
                                </div>
                                <div class="card__body">
                                    <label class="field">
                                        <span class="field__label">Nom</span>
                                        <input class="input" name="team_name" placeholder="Ex: Night Owls" required maxlength="80">
                                    </label>
                                </div>
                                <div class="card__footer">
                                    <button class="btn btn--primary" type="submit">Creer</button>
                                </div>
                            </form>

                            <form class="card card--nested form" method="post" action="/lan/<?= View::e($slug) ?>/teams/join" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div class="card__header">
                                    <h3 class="card__title">Rejoindre</h3>
                                    <p class="card__subtitle">Avec un code.</p>
                                </div>
                                <div class="card__body">
                                    <label class="field">
                                        <span class="field__label">Code</span>
                                        <input class="input mono" name="join_code" placeholder="Ex: 8KQ7M2ZP4A" required maxlength="16">
                                    </label>
                                </div>
                                <div class="card__footer">
                                    <button class="btn btn--ghost" type="submit">Rejoindre</button>
                                </div>
                            </form>
                        </div>

                        <?php if (is_int($teamSizeLimit) && $teamSizeLimit > 0): ?>
                            <div class="muted" style="margin-top: 10px;">
                                Limite roster (LAN): max <?= (int)$teamSizeLimit ?> (basee sur le plus petit team_size des tournois du LAN).
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

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
        <h2 class="section__title">Classement (LAN)</h2>
        <div class="section__meta">
            Points auto (sur <?= (int)($scoring['computedTournaments'] ?? 0) ?>/<?= (int)($scoring['totalTournaments'] ?? 0) ?> tournoi(s))
        </div>
    </div>

    <?php $lb = $scoring['leaderboard'] ?? []; ?>
    <?php if (!is_array($lb) || $lb === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun classement pour le moment</div>
            <div class="empty__hint">Le classement apparait quand des tournois du LAN ont une finale confirmée.</div>
        </div>
    <?php else: ?>
        <div class="tablewrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= $ptype === 'team' ? 'Equipe (roster)' : 'Joueur' ?></th>
                        <th class="table__right">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($lb as $row): ?>
                        <?php
                            $name = (string)($row['name'] ?? '');
                            $pts = (int)($row['points'] ?? 0);
                            $members = is_array($row['members'] ?? null) ? $row['members'] : [];
                            $membersStr = $members !== [] ? implode(', ', array_map('strval', $members)) : '';
                        ?>
                        <tr>
                            <td class="mono"><?= (int)$rank ?></td>
                            <td class="table__strong">
                                <?= View::e($name) ?>
                                <?php if ($ptype === 'team' && $membersStr !== ''): ?>
                                    <div class="muted" style="margin-top: 2px;"><?= View::e($membersStr) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="mono table__right"><?= (int)$pts ?></td>
                        </tr>
                        <?php $rank++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="muted" style="margin-top: 10px;">
            Bareme: <span class="mono">max = 100 * ceil(log2(N))</span> avec <span class="mono">N</span> entrants, et une decroissance progressive par placement.
        </div>
    <?php endif; ?>
</section>

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
                    $href = ($slug !== '' && $tslug !== '') ? ('/lan/' . $slug . '/t/' . $tslug) : ('/tournaments/' . (int)($t['id'] ?? 0));
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
