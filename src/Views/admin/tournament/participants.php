<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var 'solo'|'team' $participantType */
/** @var list<array<string,mixed>> $players */
/** @var list<array<string,mixed>> $teams */
/** @var array<int, list<array{user_id:int,username:string,role:string}>> $teamMembers */
/** @var string $csrfToken */
/** @var int $tid */
?>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Participants</h2>
        <div class="section__meta">Seeds, check-in, et retrait.</div>
    </div>

    <?php $rows = $participantType === 'team' ? $teams : $players; ?>
    <?php if ($rows === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun inscrit</div>
            <div class="empty__hint">Les joueurs s'inscrivent depuis la page publique du tournoi.</div>
        </div>
    <?php else: ?>
        <div class="tablewrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <?php if ($participantType === 'team'): ?>
                            <th>Equipe</th>
                            <th>Membres</th>
                        <?php else: ?>
                            <th>Joueur</th>
                        <?php endif; ?>
                        <th>Seed</th>
                        <th>Check-in</th>
                        <th>Inscrit le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($participantType === 'team'): ?>
                        <?php foreach ($teams as $t): ?>
                            <?php
                                $teamId = (int)($t['team_id'] ?? 0);
                                $seed = $t['seed'] !== null ? (int)$t['seed'] : null;
                                $checkedIn = (int)($t['checked_in'] ?? 0) === 1;
                                $members = $teamMembers[$teamId] ?? [];
                            ?>
                            <tr>
                                <td class="table__strong"><?= View::e((string)($t['name'] ?? '')) ?></td>
                                <td><?= View::e(format_members_admin($members)) ?></td>
                                <td>
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/teams/<?= (int)$teamId ?>/seed" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <input class="input input--compact" type="number" inputmode="numeric" name="seed" min="1" step="1" value="<?= $seed !== null ? (int)$seed : '' ?>" placeholder="-">
                                        <button class="btn btn--ghost btn--compact" type="submit">Appliquer</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="pill<?= $checkedIn ? '' : ' pill--soft' ?>"><?= $checkedIn ? 'OK' : 'non' ?></span>
                                    <?php if ($checkedIn): ?>
                                        <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/teams/<?= (int)$teamId ?>/checkin" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input type="hidden" name="checked_in" value="0">
                                            <button class="btn btn--ghost btn--compact" type="submit">Annuler</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/teams/<?= (int)$teamId ?>/checkin" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input type="hidden" name="checked_in" value="1">
                                            <button class="btn btn--primary btn--compact" type="submit">Check-in</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><?= View::e((string)($t['joined_at'] ?? '')) ?></td>
                                <td class="table__right">
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/teams/<?= (int)$teamId ?>/remove" class="inline" data-confirm="Retirer cette equipe du tournoi ?">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button class="btn btn--danger btn--compact" type="submit">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($players as $p): ?>
                            <?php
                                $pid = (int)($p['player_id'] ?? 0);
                                $seed = $p['seed'] !== null ? (int)$p['seed'] : null;
                                $checkedIn = (int)($p['checked_in'] ?? 0) === 1;
                            ?>
                            <tr>
                                <td class="table__strong"><?= View::e((string)($p['handle'] ?? '')) ?></td>
                                <td>
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/players/<?= (int)$pid ?>/seed" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <input class="input input--compact" type="number" inputmode="numeric" name="seed" min="1" step="1" value="<?= $seed !== null ? (int)$seed : '' ?>" placeholder="-">
                                        <button class="btn btn--ghost btn--compact" type="submit">Appliquer</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="pill<?= $checkedIn ? '' : ' pill--soft' ?>"><?= $checkedIn ? 'OK' : 'non' ?></span>
                                    <?php if ($checkedIn): ?>
                                        <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/players/<?= (int)$pid ?>/checkin" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input type="hidden" name="checked_in" value="0">
                                            <button class="btn btn--ghost btn--compact" type="submit">Annuler</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/players/<?= (int)$pid ?>/checkin" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input type="hidden" name="checked_in" value="1">
                                            <button class="btn btn--primary btn--compact" type="submit">Check-in</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><?= View::e((string)($p['joined_at'] ?? '')) ?></td>
                                <td class="table__right">
                                    <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/players/<?= (int)$pid ?>/remove" class="inline" data-confirm="Retirer ce joueur du tournoi ?">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button class="btn btn--danger btn--compact" type="submit">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

