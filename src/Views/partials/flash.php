<?php

declare(strict_types=1);

use DuelDesk\Support\Flash;
use DuelDesk\View;

$success = Flash::get('success');
$error = Flash::get('error');
?>

<?php if ($success !== null): ?>
    <div class="alert alert--success" role="status">
        <div class="alert__icon" aria-hidden="true"></div>
        <div class="alert__text"><?= View::e($success) ?></div>
    </div>
<?php endif; ?>

<?php if ($error !== null): ?>
    <div class="alert alert--error" role="alert">
        <div class="alert__icon" aria-hidden="true"></div>
        <div class="alert__text"><?= View::e($error) ?></div>
    </div>
<?php endif; ?>
