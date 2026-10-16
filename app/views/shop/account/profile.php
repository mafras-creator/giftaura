<?php
/** My account - profile. Variables: $user, $errors, $old. */
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="panel">
            <h2 class="panel__title">Profile details</h2>

            <form method="post" action="<?= e(url('account/')) ?>" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-field">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name'] ?? $user['full_name']) ?>"
                           autocomplete="name" required maxlength="100"<?= field_attrs($errors, 'full_name') ?>>
                    <?= field_error($errors, 'full_name') ?>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="email">Email (used to log in)</label>
                        <input type="email" id="email" value="<?= e($user['email']) ?>" readonly disabled>
                    </div>
                    <div class="form-field">
                        <label for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? $user['phone'] ?? '') ?>"
                               autocomplete="tel" required maxlength="20"<?= field_attrs($errors, 'phone') ?>>
                        <?= field_error($errors, 'phone') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">Save changes</button>
            </form>

            <dl class="meta-list">
                <div><dt>Member since</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd></div>
                <?php if (!empty($user['last_login_at'])): ?>
                    <div><dt>Last login</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</section>
