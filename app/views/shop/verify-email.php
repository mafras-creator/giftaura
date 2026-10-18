<?php
/** Verify e-mail. Variables: $token, $state ('confirm' | 'invalid'). */
$user = current_user();
?>
<section class="auth">
    <div class="auth__card">
        <?php if ($state === 'confirm'): ?>
            <h1 class="auth__title">Verify your e-mail</h1>
            <p class="auth__lead">Click the button to confirm your e-mail address.</p>
            <form method="post" action="<?= e(url('verify-email.php')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="btn btn--primary btn--block">Confirm my e-mail address</button>
            </form>
        <?php else: ?>
            <h1 class="auth__title">This link does not work</h1>
            <div class="alert alert--error" role="alert"><span>The verification link is invalid, has expired (after 24 hours) or has already been used.</span></div>
            <?php if ($user !== null && $user['role'] === 'customer' && $user['email_verified_at'] === null): ?>
                <form method="post" action="<?= e(url('resend-verification.php')) ?>" class="form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--block">Send me a new link</button>
                </form>
            <?php elseif ($user !== null && $user['email_verified_at'] !== null): ?>
                <p>Your e-mail address is already verified — nothing else to do.</p>
                <p><a class="btn btn--primary btn--block" href="<?= e(url('account/')) ?>">Go to my account</a></p>
            <?php else: ?>
                <p>Log in and use <strong>Send the link again</strong> at the top of the page to get a new link.</p>
                <p><a class="btn btn--primary btn--block" href="<?= e(url('login.php')) ?>">Log in</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
