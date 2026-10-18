<?php
/** Order placed. Variables: $order, $items, $payment, $bank. */
?>
<section class="section">
    <div class="container confirmation">
        <div class="confirmation__badge" aria-hidden="true">&#10003;</div>
        <h1 class="page-title">Thank you, your order has been placed</h1>
        <p class="page-lead">Your order reference is</p>
        <p class="order-ref"><?= e($order['order_number']) ?></p>
        <p class="muted">Keep this reference. You can follow the order in <a href="<?= e(url('account/orders.php')) ?>">My orders</a>.</p>

        <div class="panel confirmation__panel">
            <h2 class="panel__title">Summary</h2>
            <ul class="summary-lines">
                <?php foreach ($items as $item): ?>
                    <li><span><?= e($item['product_name']) ?> <span class="muted">&times; <?= e((string) $item['quantity']) ?></span></span><span><?= e(money($item['line_total'])) ?></span><?= View::partial('custom-note', ['text' => (string) ($item['customization_text'] ?? '')]) ?></li>
                <?php endforeach; ?>
            </ul>
            <dl class="totals">
                <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= (float) $order['delivery_fee'] > 0 ? e(money($order['delivery_fee'])) : 'Free' ?></dd></div>
                <div class="totals__grand"><dt>Total</dt><dd><?= e(money($order['total_amount'])) ?></dd></div>
            </dl>
            <p><strong>Payment:</strong> <?= e(PaymentService::METHODS[$payment['method']] ?? '') ?> &middot; <?= payment_status_badge($payment['status'] ?? null) ?></p>
            <p><strong>Deliver to:</strong> <?= e($order['recipient_name']) ?>, <?= e($order['address_line1']) ?>, <?= e($order['city']) ?>, <?= e($order['district']) ?></p>

            <?php if (($payment['method'] ?? '') === 'bank_transfer'): ?>
                <?= View::partial('bank-details', ['bank' => $bank, 'order' => $order]) ?>
            <?php else: ?>
                <p class="muted">Please have <?= e(money($order['total_amount'])) ?> ready in cash when your order is delivered.</p>
            <?php endif; ?>
        </div>

        <div class="confirmation__actions">
            <a class="btn btn--primary" href="<?= e(url('account/order.php', ['order' => $order['order_number']])) ?>">View order details</a>
            <a class="btn btn--outline" href="<?= e(url('products.php')) ?>">Continue shopping</a>
        </div>
    </div>
</section>
