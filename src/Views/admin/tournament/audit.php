<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var int $tid */
/** @var list<array<string,mixed>> $auditLogs */
?>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Audit</h2>
        <div class="section__meta">Actions recentes</div>
    </div>

    <?php if ($auditLogs === []): ?>
        <div class="empty empty--compact">
            <div class="empty__title">Aucun log</div>
            <div class="empty__hint">Les actions admin (bracket, confirmations, etc.) apparaitront ici.</div>
        </div>
    <?php else: ?>
        <div class="tablewrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Cible</th>
                        <th>Meta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditLogs as $a): ?>
                        <?php
                            $when = (string)($a['created_at'] ?? '');
                            $who = (string)($a['username'] ?? '');
                            $action = (string)($a['action'] ?? '');
                            $etype = (string)($a['entity_type'] ?? '');
                            $eid = $a['entity_id'] !== null ? (int)$a['entity_id'] : 0;
                            $metaJson = (string)($a['meta_json'] ?? '');

                            $metaLabel = '';
                            if ($metaJson !== '') {
                                $decoded = json_decode($metaJson, true);
                                if (is_array($decoded)) {
                                    $pairs = [];
                                    foreach (['status', 'score1', 'score2', 'winner_slot', 'bracket', 'round', 'round_pos', 'note'] as $k) {
                                        if (!array_key_exists($k, $decoded)) {
                                            continue;
                                        }
                                        $v = $decoded[$k];
                                        if (is_bool($v)) {
                                            $v = $v ? 'true' : 'false';
                                        } elseif (is_array($v) || is_object($v)) {
                                            $v = '[...]';
                                        }
                                        $pairs[] = $k . '=' . (string)$v;
                                    }
                                    $metaLabel = $pairs !== [] ? implode(', ', $pairs) : substr($metaJson, 0, 160);
                                } else {
                                    $metaLabel = substr($metaJson, 0, 160);
                                }
                            }
                        ?>
                        <tr>
                            <td class="mono"><?= View::e($when) ?></td>
                            <td><?= View::e($who !== '' ? $who : '-') ?></td>
                            <td class="mono"><?= View::e($action !== '' ? $action : '-') ?></td>
                            <td class="mono">
                                <?php if ($etype === 'match' && $eid > 0): ?>
                                    <a href="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$eid ?>">match#<?= (int)$eid ?></a>
                                <?php elseif ($etype !== '' && $eid > 0): ?>
                                    <?= View::e($etype) ?>#<?= (int)$eid ?>
                                <?php elseif ($etype !== ''): ?>
                                    <?= View::e($etype) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="muted"><?= View::e($metaLabel) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

