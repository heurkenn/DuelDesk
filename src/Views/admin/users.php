<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var list<array<string, mixed>> $users */
/** @var string $csrfToken */
/** @var int $meId */
/** @var int $adminCount */
/** @var string $query */
/** @var int $page */
/** @var int $pages */
/** @var int $total */

function admin_users_page_link(int $page, string $query): string
{
    $params = ['page' => max(1, $page)];
    $q = trim($query);
    if ($q !== '') {
        $params['q'] = $q;
    }

    return '/admin/users?' . http_build_query($params);
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Utilisateurs</h1>
        <p class="pagehead__lead">Roles: super_admin / admin / user.</p>
    </div>
    <div class="pagehead__actions">
        <form method="get" action="/admin/users" class="inline">
            <input class="input input--compact" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Rechercher..." maxlength="80">
            <button class="btn btn--ghost btn--compact" type="submit">OK</button>
            <?php if (trim($query) !== ''): ?>
                <a class="btn btn--ghost btn--compact" href="/admin/users">Reset</a>
            <?php endif; ?>
        </form>
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
                $isSuper = ($role === 'super_admin');
                $canManage = Auth::isSuperAdmin() && !$isMe && !$isSuper;
            ?>
            <tr>
                <td class="table__strong">
                    <span class="mono"><?= View::e((string)($u['username'] ?? '')) ?></span>
                    <?php if ($isMe): ?>
                        <span class="pill pill--soft">toi</span>
                    <?php endif; ?>
                    <?php if ($isSuper): ?>
                        <span class="pill">super</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($role === 'super_admin'): ?>
                        <span class="pill">super_admin</span>
                    <?php else: ?>
                        <form method="post" action="/admin/users/<?= $id ?>/role" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <select class="select select--compact" name="role" <?= $canManage ? '' : 'disabled' ?>>
                                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>user</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>admin</option>
                            </select>
                            <button class="btn btn--ghost btn--compact" type="submit" <?= $canManage ? '' : 'disabled' ?>>Appliquer</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= View::e((string)($u['created_at'] ?? '')) ?></td>
                <td class="table__right">
                    <?php if (Auth::isSuperAdmin() && !$isMe && !$isSuper): ?>
                        <form method="post" action="/admin/users/<?= $id ?>/delete" class="inline" data-confirm="Supprimer cet utilisateur definitivement ?">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn--ghost btn--compact" type="submit">Supprimer</button>
                        </form>
                    <?php endif; ?>
                    <span class="muted">#<?= $id ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <div class="inline" style="margin-top: 12px; justify-content: space-between; width: 100%;">
        <span class="muted">Page <?= (int)$page ?> / <?= (int)$pages ?> (<?= (int)$total ?> total)</span>
        <div class="inline">
            <?php if ($page > 1): ?>
                <a class="btn btn--ghost btn--compact" href="<?= View::e(admin_users_page_link($page - 1, $query)) ?>">Prev</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="btn btn--ghost btn--compact" href="<?= View::e(admin_users_page_link($page + 1, $query)) ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
