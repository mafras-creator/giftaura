<?php
/** My account - profile card (igp-style). Variables: $user, $errors, $old, $editing, $place, $stats. */
$name = trim((string) $user['full_name']);
$initial = mb_strtoupper(mb_substr($name !== '' ? $name : 'G', 0, 1));
$verified = !empty($user['email_verified_at']);
$location = $place !== null ? $place['city'] . ($place['district'] !== $place['city'] ? ', ' . $place['district'] : '') . ', Sri Lanka' : 'Sri Lanka';
$s = 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
?>
<section class="section account-section">
    <div class="container account-layout">
        <?= View::partial('account-nav') ?>

        <div class="account-stack">
            <div class="profile-card">
                <div class="profile-card__avatar" aria-hidden="true"><?= e($initial) ?></div>
                <div class="profile-card__body">
                    <div class="profile-card__head">
                        <h2 class="profile-card__name"><?= e($name) ?></h2>
                        <span class="profile-card__tag"><?= $verified ? '(Verified)' : '(E-mail not verified)' ?></span>
                        <?php if (!$editing): ?>
                            <a class="profile-card__edit" href="<?= e(url('account/', ['edit' => 1])) ?>">Edit</a>
                        <?php endif; ?>
                    </div>
                    <ul class="profile-card__facts">
                        <li><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="3" y="5.5" width="18" height="13" rx="2" <?= $s ?>/><path d="M3.5 7l8.5 6 8.5-6" <?= $s ?>/></svg><span class="sr-only">E-mail: </span><?= e((string) $user['email']) ?></li>
                        <?php if (!empty($user['phone'])): ?>
                            <li><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2" <?= $s ?>/><path d="M11 18.5h2" <?= $s ?>/></svg><span class="sr-only">Mobile: </span><?= e((string) $user['phone']) ?></li>
                        <?php endif; ?>
                        <li><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z" <?= $s ?>/><circle cx="12" cy="10" r="2.4" <?= $s ?>/></svg><span class="sr-only">Location: </span><?= e($location) ?></li>
                    </ul>
                </div>
            </div>

            <?php if ($editing): ?>
                <div class="panel">
                    <h2 class="panel__title">Edit profile</h2>
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
                                <input type="email" id="email" value="<?= e((string) $user['email']) ?>" readonly disabled>
                            </div>
                            <div class="form-field">
                                <label for="phone">Mobile number</label>
                                <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? $user['phone'] ?? '') ?>"
                                       autocomplete="tel" required maxlength="20"<?= field_attrs($errors, 'phone') ?>>
                                <?= field_error($errors, 'phone') ?>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn--primary">Save changes</button>
                            <a class="btn btn--ghost" href="<?= e(url('account/')) ?>">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <ul class="profile-stats">
                    <li><a href="<?= e(url('account/orders.php')) ?>"><strong><?= e((string) $stats['orders']) ?></strong><span>Order<?= $stats['orders'] === 1 ? '' : 's' ?></span></a></li>
                    <li><a href="<?= e(url('account/wishlist.php')) ?>"><strong><?= e((string) $stats['wishlist']) ?></strong><span>In wishlist</span></a></li>
                    <li><a href="<?= e(url('account/reviews.php')) ?>"><strong><?= e((string) $stats['reviews']) ?></strong><span>Review<?= $stats['reviews'] === 1 ? '' : 's' ?></span></a></li>
                </ul>
            <?php endif; ?>

            <dl class="meta-list profile-meta">
                <div><dt>Member since</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd></div>
                <?php if (!empty($user['last_login_at'])): ?>
                    <div><dt>Last login</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</section>
