<?php
/**
 * Track an order. Variables: $user, $number, $email, $order, $error, $history, $payment, $recent.
 * For privacy, a lookup by reference + email shows only status, dates and totals.
 */
?>
<div class="page-head">
    <div class="container">
        <h1 class="page-title">Track your order</h1>
        <p class="page-lead">Enter your order reference and the email address used for the order.</p>
    </div>
</div>

<div class="container section--tight track-layout">
    <div class="panel">
        <?php if ($error): ?>
            <div class="alert alert--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('track-order.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="order">Order reference</label>
                <input type="text" id="order" name="order" value="<?= e($number) ?>" maxlength="20" required placeholder="GA261016-ABC123" autocapitalize="characters">
            </div>
            <div class="form-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="190" required autocomplete="email">
            </div>
            <button type="submit" class="btn btn--primary">Track order</button>
        </form>

        <?php if ($user !== null && $recent !== []): ?>
            <div class="recent-orders">
                <h2 class="filter-block__title">Your recent orders</h2>
                <ul>
                    <?php foreach ($recent as $r): ?>
                        <li><a href="<?= e(url('account/order.php', ['order' => $r['order_number']])) ?>"><?= e($r['order_number']) ?></a> &middot; <?= order_status_badge($r['status']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($order !== null): ?>
        <div class="panel">
            <div class="order-head">
                <div>
                    <h2 class="panel__title">Order <?= e($order['order_number']) ?></h2>
                    <p class="muted">Placed on <?= e(format_datetime($order['created_at'])) ?> &middot; Total <?= e(money($order['total_amount'])) ?></p>
                </div>
                <div><?= order_status_badge($order['status']) ?></div>
            </div>
            <?= View::partial('order-progress', ['order' => $order, 'history' => $history]) ?>
            <p>Payment: <?= e(PaymentService::METHODS[$payment['method'] ?? ''] ?? '') ?> &middot; <?= payment_status_badge($payment['status'] ?? null) ?></p>
            <p class="muted">For full details, <a href="<?= e(url('login.php')) ?>">log in</a> to your account.</p>
        </div>
    <?php endif; ?>
</div>
