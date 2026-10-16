<?php
/** Contact page. Variables: $phone, $whatsapp, $email, $address, $hours (from settings). */
$hasAny = ($phone . $whatsapp . $email . $address . $hours) !== '';
?>
<div class="page-head">
    <div class="container">
        <h1 class="page-title">Contact us</h1>
        <p class="page-lead">Questions about a gift or an order? Here is how to reach <?= e(setting('store_name', 'GiftAura')) ?>.</p>
    </div>
</div>

<section class="section section--tight">
    <div class="container contact-grid">
        <?php if (!$hasAny): ?>
            <div class="empty-state">
                <p><strong>Store contact details have not been published yet.</strong></p>
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
</section>
