<?php
/** Contact page. Variables: $phone, $whatsapp, $email, $address, $hours (from settings), $errors, $old, $user. */
$hasAny = ($phone . $whatsapp . $email . $address . $hours) !== '';
?>
<div class="page-head">
    <div class="container">
        <h1 class="page-title">Contact us</h1>
        <p class="page-lead">Questions about a gift or an order? Here is how to reach <?= e(setting('store_name', 'GiftAura')) ?>.</p>
    </div>
</div>

<section class="section section--tight">
    <div class="container contact-layout">
    <div class="contact-grid">
        <?php if (!$hasAny): ?>
            <div class="empty-state">
                <p><strong>Use the form to send us a message.</strong></p>
                <p>If you have an account, you can follow your orders from <a href="<?= e(url('account/')) ?>">My account</a>.</p>
            </div>
        <?php else: ?>
            <?php if ($phone !== ''): ?>
                <div class="contact-card">
                    <h2 class="contact-card__title">Phone</h2>
                    <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                </div>
            <?php endif; ?>
            <?php if ($whatsapp !== ''): ?>
                <div class="contact-card">
                    <h2 class="contact-card__title">WhatsApp</h2>
                    <p><?= e($whatsapp) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($email !== ''): ?>
                <div class="contact-card">
                    <h2 class="contact-card__title">Email</h2>
                    <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                </div>
            <?php endif; ?>
            <?php if ($address !== ''): ?>
                <div class="contact-card">
                    <h2 class="contact-card__title">Visit the store</h2>
                    <p class="pre-line"><?= e($address) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($hours !== ''): ?>
                <div class="contact-card">
                    <h2 class="contact-card__title">Opening hours</h2>
                    <p class="pre-line"><?= e($hours) ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="panel contact-form" id="message">
        <h2 class="panel__title">Send us a message</h2>
        <p class="muted">Questions about a gift, a personalised order or a delivery? We reply by e-mail<?= $user !== null && $user['role'] === 'customer' ? ' and under My account → My inquiries' : '' ?>.</p>
        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert"><span>Please correct the highlighted fields.</span></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('contact.php')) ?>#message" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-field">
                    <label for="name">Your name</label>
                    <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="100" autocomplete="name" required<?= field_attrs($errors, 'name') ?>>
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="form-field">
                    <label for="email">Your e-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="190" autocomplete="email" required<?= field_attrs($errors, 'email') ?>>
                    <?= field_error($errors, 'email') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="phone">Mobile number <span class="optional">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" autocomplete="tel"<?= field_attrs($errors, 'phone') ?>>
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="form-field">
                    <label for="order_number">Order reference <span class="optional">(optional)</span></label>
                    <input type="text" id="order_number" name="order_number" value="<?= e($old['order_number'] ?? '') ?>" maxlength="20" placeholder="e.g. GA261018-KLH46R"<?= field_attrs($errors, 'order_number') ?>>
                    <?= field_error($errors, 'order_number') ?>
                </div>
            </div>
            <div class="form-field">
                <label for="subject">What is it about?</label>
                <select id="subject" name="subject" required<?= field_attrs($errors, 'subject') ?>>
                    <option value="">Choose…</option>
                    <?php foreach (InquiryService::SUBJECTS as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= ($old['subject'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'subject') ?>
            </div>
            <div class="form-field">
                <label for="message">Your message</label>
                <textarea id="message" name="message" rows="6" maxlength="<?= e((string) InquiryService::MESSAGE_MAX) ?>" data-char-count required<?= field_attrs($errors, 'message') ?>><?= e($old['message'] ?? '') ?></textarea>
                <p class="personalise__meta"><span>Please do not send card or bank passwords.</span><span data-char-counter aria-live="polite"></span></p>
                <?= field_error($errors, 'message') ?>
            </div>
            <div class="hp-field" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
            </div>
            <button type="submit" class="btn btn--primary" data-loading-text="Sending…">Send message</button>
        </form>
    </div>
    </div>
</section>
