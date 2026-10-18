<?php
/** Reset password. Variables: $token, $valid, $errors. */
?>
<section class="auth">
    <div class="auth__card">
        <h1 class="auth__title">Choose a new password</h1>
        <?php if (!$valid): ?>
            <div class="alert alert--error" role="alert"><span>This password-reset link is invalid, has expired (after 60 minutes) or has already been used.</span></div>
            <p><a class="btn btn--primary btn--block" href="<?= e(url('forgot-password.php')) ?>">Ask for a new link</a></p>
        <?php else: ?>
            <form method="post" action="<?= e(url('reset-password.php')) ?>" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-field">
                    <label for="new_password">New password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                               required minlength="8" maxlength="72" autofocus<?= field_attrs($errors, 'new_password') ?>>
                        <button type="button" class="password-field__toggle" data-toggle-password="new_password">Show</button>
                    </div>
                    <?= field_error($errors, 'new_password') ?>
                    <p class="form-hint">At least 8 characters, including a letter and a number.</p>
                </div>
                <div class="form-field">
                    <label for="new_password_confirm">Confirm new password</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password"
                           required maxlength="72"<?= field_attrs($errors, 'new_password_confirm') ?>>
                    <?= field_error($errors, 'new_password_confirm') ?>
                </div>
                <button type="submit" class="btn btn--primary btn--block">Save new password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
