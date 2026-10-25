<?php
/** Login form. Variables: $error (string|null), $old (array), $next (string), $waiting (product name|null). */
?>
<section class="auth">
    <div class="auth__card">
        <h1 class="auth__title">Welcome back</h1>
        <p class="auth__lead">Log in to your GiftAura account.</p>

        <?php if (!empty($waiting)): ?>
            <div class="cart-waiting" role="status">
                <p class="cart-waiting__title">Almost there!</p>
                <p> Log in or create a free account and <strong><?= e($waiting) ?></strong> will be added to your cart automatically.</p>
                <p><a class="btn btn--outline btn--block" href="<?= e(url('register.php')) ?>">New customer? Create an account</a></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="form-field">
                <label for="email">Email <span class="optional">(staff: username)</span></label>
                <input type="text" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
                       autocomplete="username" inputmode="email" required maxlength="190" autofocus>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" autocomplete="current-password" required maxlength="72">
                    <button type="button" class="password-field__toggle" data-toggle-password="password">Show</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block" data-loading-text="Logging in…">Log in</button>
        </form>

        <p class="auth__switch">New to GiftAura? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
        <p class="auth__help"><a href="<?= e(url('forgot-password.php')) ?>">Forgotten your password?</a></p>
    </div>
</section>
