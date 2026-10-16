<?php
/** Login form. Variables: $error (string|null), $old (array), $next (string). */
?>
<section class="auth">
    <div class="auth__card">
        <h1 class="auth__title">Welcome back</h1>
        <p class="auth__lead">Log in to your GiftAura account.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="form-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
                       autocomplete="email" required maxlength="190" autofocus>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" autocomplete="current-password" required maxlength="72">
                    <button type="button" class="password-field__toggle" data-toggle-password="password">Show</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Log in</button>
        </form>

        <p class="auth__switch">New to GiftAura? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
        <p class="auth__help">Forgotten your password? Please contact the store and an administrator will set a temporary password for you.</p>
    </div>
</section>
