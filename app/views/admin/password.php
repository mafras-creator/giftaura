<?php
/** Admin - change own password. Variables: $user, $errors, $forced. */
?>
<?php if ($forced): ?>
    <div class="alert alert--warning"><span>You are using a password created by the main administrator. Choose your own password to continue.</span></div>
<?php endif; ?>
<section class="admin-panel admin-panel--narrow">
    <header class="admin-panel__header"><h2 class="admin-panel__title">Change your password</h2></header>
    <form method="post" action="<?= e(url('admin/password.php')) ?>" class="admin-form" novalidate>
        <?= csrf_field() ?>
        <?php if (($user['username'] ?? null) !== null): ?>
            <p class="muted">Username: <strong><?= e($user['username']) ?></strong></p>
        <?php endif; ?>
        <div class="field">
            <label for="current_password"><?= $forced ? 'Password you were given' : 'Current password' ?></label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required maxlength="72"<?= field_attrs($errors, 'current_password') ?>>
            <?= field_error($errors, 'current_password') ?>
        </div>
        <div class="field">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="8" maxlength="72"<?= field_attrs($errors, 'new_password') ?>>
            <?= field_error($errors, 'new_password') ?>
            <p class="hint">At least 8 characters, including a letter and a number. Do not share it with anyone.</p>
        </div>
        <div class="field">
            <label for="new_password_confirm">Confirm new password</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required maxlength="72"<?= field_attrs($errors, 'new_password_confirm') ?>>
            <?= field_error($errors, 'new_password_confirm') ?>
        </div>
        <button type="submit" class="btn btn--primary">Save new password</button>
    </form>
</section>
