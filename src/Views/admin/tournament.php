<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string, mixed> $tournament */
/** @var string $activeTab */
/** @var string|null $matchModeWarning */
/** @var list<array<string, mixed>> $players */
/** @var list<array<string, mixed>> $teams */
/** @var array<int, list<array{user_id:int,username:string,role:string}>> $teamMembers */
/** @var list<array<string, mixed>> $games */
/** @var list<array<string, mixed>> $lanEvents */
/** @var list<array<string, mixed>> $rulesets */
/** @var string $csrfToken */
/** @var string $startsAtValue */
/** @var string $maxEntrantsValue */
/** @var string $signupClosesValue */
/** @var string $bestOfDefaultValue */
/** @var string $bestOfFinalValue */
/** @var int $matchCount */
/** @var int $confirmedCount */
/** @var bool $canGenerateBracket */
/** @var list<string> $incompleteTeams */
/** @var list<array<string, mixed>> $matches */
/** @var list<array<string, mixed>> $auditLogs */

$tid = (int)($tournament['id'] ?? 0);
$gameId = (int)($tournament['game_id'] ?? 0);
$participantType = (string)($tournament['participant_type'] ?? 'solo');
$teamSize = (int)($tournament['team_size'] ?? 0);
$format = (string)($tournament['format'] ?? 'single_elim');
$teamMatchMode = (string)($tournament['team_match_mode'] ?? 'standard');
$canEditStructure = (int)$confirmedCount === 0;
$status = (string)($tournament['status'] ?? 'draft');

$tabs = [
    'participants' => 'Participants',
    'matches' => 'Matchs',
    'settings' => 'Parametres',
    'audit' => 'Audit',
];
if (!isset($tabs[$activeTab])) {
    $activeTab = 'participants';
}

/**
 * @param list<array{user_id:int,username:string,role:string}> $members
 */
function format_members_admin(array $members): string
{
    if ($members === []) {
        return '-';
    }

    $names = [];
    foreach ($members as $m) {
        $name = (string)($m['username'] ?? '');
        if ($name === '') {
            continue;
        }
        $names[] = ($m['role'] ?? '') === 'captain' ? ($name . ' (c)') : $name;
    }

    return $names !== [] ? implode(', ', $names) : '-';
}

function to_datetime_local_admin(mixed $dbValue): string
{
    if (!is_string($dbValue) || $dbValue === '') {
        return '';
    }

    // DB: YYYY-MM-DD HH:MM:SS -> input: YYYY-MM-DDTHH:MM
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $dbValue)) {
        return '';
    }

    $v = substr($dbValue, 0, 16);
    if ($v === false) {
        return '';
    }

    return str_replace(' ', 'T', $v);
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Gerer le tournoi</h1>
        <p class="pagehead__lead">
            <?php if (!empty($tournament['game_image_path'])): ?>
                <img class="gameicon" src="<?= View::e((string)$tournament['game_image_path']) ?>" alt="" loading="lazy" width="22" height="22">
            <?php endif; ?>
            <span class="table__strong"><?= View::e((string)($tournament['name'] ?? '')) ?></span>
            <span class="meta__dot" aria-hidden="true"></span>
            <?= View::e((string)($tournament['game'] ?? '')) ?>
            <?php if (!empty($tournament['lan_event_id'])): ?>
                <span class="meta__dot" aria-hidden="true"></span>
                <a class="pill pill--soft" href="/admin/lan/<?= (int)$tournament['lan_event_id'] ?>">
                    LAN: <?= View::e((string)($tournament['lan_event_name'] ?? ('#' . (int)$tournament['lan_event_id']))) ?>
                </a>
            <?php endif; ?>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill"><?= View::e((string)($tournament['format'] ?? '')) ?></span>
            <span class="pill pill--soft"><?= View::e($status) ?></span>
        </p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/tournaments/<?= (int)$tid ?>">Voir la page</a>
        <a class="btn btn--ghost" href="/admin">Retour admin</a>
    </div>
</div>

<nav class="paneltabs" aria-label="Navigation tournoi admin" style="margin-top: 12px;">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="paneltabs__tab<?= $activeTab === $key ? ' is-active' : '' ?>" href="/admin/tournaments/<?= (int)$tid ?>?tab=<?= View::e($key) ?>">
            <?= View::e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (is_string($matchModeWarning) && $matchModeWarning !== ''): ?>
    <div class="alert alert--error" role="status" style="margin-top: 12px;">
        <div class="alert__icon" aria-hidden="true"></div>
        <div class="alert__text"><?= View::e($matchModeWarning) ?></div>
    </div>
<?php endif; ?>

<?php
    $base = __DIR__ . '/tournament';
    if ($activeTab === 'matches') {
        require $base . '/matches.php';
    } elseif ($activeTab === 'settings') {
        require $base . '/settings.php';
    } elseif ($activeTab === 'audit') {
        require $base . '/audit.php';
    } else {
        require $base . '/participants.php';
    }
?>
