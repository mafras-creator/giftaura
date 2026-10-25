<?php
/** My account - change password. Variables: $user, $errors. */
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="panel">
            <h2 class="panel__title">Change password</h2>

            <form method="post" action="<?= e(url('account/password.php')) ?>" class="form form--narrow" novalidate>
                <?= csrf_field() ?>

                <div class="form-field">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                           required maxlength="72"<?= field_attrs($errors, 'current_password') ?>>
                    <?= field_error($errors, 'current_password') ?>
                </div>

                <div class="form-field">
                    <label for="new_password">New password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                               required minlength="8" maxlength="72"<?= field_attrs($errors, 'new_password') ?>>
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

                <button type="submit" class="btn btn--primary">Update password</button>
            </form>
        </div>
    </div>
</section>
