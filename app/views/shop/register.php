<?php
/** Registration form. Variables: $errors (array), $old (array). */
?>
<section class="auth">
    <div class="auth__card auth__card--wide">
        <h1 class="auth__title">Create your account</h1>
        <p class="auth__lead">Save your details, check out faster and track your orders.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error" role="alert"><span>Please correct the highlighted fields.</span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name'] ?? '') ?>"
                       autocomplete="name" required maxlength="100"<?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
                           autocomplete="email" required maxlength="190"<?= field_attrs($errors, 'email') ?>>
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="form-field">
                    <label for="phone">Mobile number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>"
                           autocomplete="tel" required maxlength="20" placeholder="0771234567"<?= field_attrs($errors, 'phone') ?>>
                    <?= field_error($errors, 'phone') ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" autocomplete="new-password"
                               required minlength="8" maxlength="72"<?= field_attrs($errors, 'password') ?>>
                        <button type="button" class="password-field__toggle" data-toggle-password="password">Show</button>
                    </div>
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="form-field">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
                           required maxlength="72"<?= field_attrs($errors, 'password_confirm') ?>>
                    <?= field_error($errors, 'password_confirm') ?>
                </div>
            </div>
            <p class="form-hint">At least 8 characters, including a letter and a number.</p>

            <button type="submit" class="btn btn--primary btn--block" data-loading-text="Creating your account…">Create account</button>
        </form>

        <p class="auth__switch">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
</section>
