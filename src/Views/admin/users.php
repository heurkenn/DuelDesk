<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var list<array<string, mixed>> $users */
/** @var string $csrfToken */
/** @var int $meId */
/** @var int $adminCount */
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Utilisateurs</h1>
        <p class="pagehead__lead">Gestion des roles (admin / user).</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin">Retour</a>
    </div>
</div>

<div class="tablewrap">
    <table class="table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Cree le</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php
                $id = (int)($u['id'] ?? 0);
                $role = (string)($u['role'] ?? 'user');
                $isMe = $id === $meId;
                $isLastAdmin = ($role === 'admin' && $adminCount <= 1);
                $lockRole = $isMe || $isLastAdmin;
            ?>
            <tr>
                <td class="table__strong">
                    <span class="mono"><?= View::e((string)($u['username'] ?? '')) ?></span>
                    <?php if ($isMe): ?>
                        <span class="pill pill--soft">toi</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="/admin/users/<?= $id ?>/role" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <select class="select select--compact" name="role" <?= $lockRole ? 'disabled' : '' ?>>
                            <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>user</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>admin</option>
                        </select>
                        <button class="btn btn--ghost btn--compact" type="submit" <?= $lockRole ? 'disabled' : '' ?>>Appliquer</button>
                        <?php if ($isLastAdmin): ?>
                            <span class="muted">dernier admin</span>
                        <?php endif; ?>
                    </form>
                </td>
                <td><?= View::e((string)($u['created_at'] ?? '')) ?></td>
                <td class="table__right"><span class="muted">#<?= $id ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
