<?php
/** Forgotten password. Variables: $error, $sent, $email. */
?>
<section class="auth">
    <div class="auth__card">
        <h1 class="auth__title">Forgotten password</h1>
        <?php if ($sent): ?>
            <div class="alert alert--success" role="status"><span>If an account exists for <?= e($email) ?>, we have sent it a link to choose a new password. The link expires in 60 minutes. Check the Spam folder too.</span></div>
            <p class="auth__switch"><a href="<?= e(url('login.php')) ?>">Back to log in</a></p>
        <?php else: ?>
            <p class="auth__lead">Enter the e-mail address of your account and we will send you a link to choose a new password.</p>
            <?php if ($error !== null): ?>
                <div class="alert alert--error" role="alert"><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('forgot-password.php')) ?>" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required maxlength="190" autofocus>
                </div>
                <button type="submit" class="btn btn--primary btn--block" data-loading-text="Sending…">Send reset link</button>
            </form>
            <p class="auth__switch"><a href="<?= e(url('login.php')) ?>">Back to log in</a></p>
            <p class="auth__help">This page is for customer accounts. Still stuck? Contact the store.</p>
        <?php endif; ?>
    </div>
</section>
