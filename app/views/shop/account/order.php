<?php
/** One order. Variables: $user, $order, $items, $history, $payment, $bank. */
$canCancel = $order['status'] === 'pending';
$canSendReference = ($payment['method'] ?? '') === 'bank_transfer'
    && $order['status'] !== 'cancelled'
    && in_array($payment['status'] ?? '', ['pending', 'failed', 'awaiting_verification'], true);
?>
<section class="section">
    <div class="container account-layout">
        <div>
            <h1 class="page-title">My account</h1>
            <p class="page-lead">Hello, <?= e($user['full_name']) ?>.</p>
            <?= View::partial('account-nav') ?>
        </div>

        <div class="stack">
            <div class="panel">
                <div class="order-head">
                    <div>
                        <p class="muted"><a href="<?= e(url('account/orders.php')) ?>">&larr; All orders</a></p>
                        <h2 class="panel__title">Order <?= e($order['order_number']) ?></h2>
                        <p class="muted">Placed on <?= e(format_datetime($order['created_at'])) ?></p>
                    </div>
                    <div><?= order_status_badge($order['status']) ?></div>
                </div>
                <?= View::partial('order-progress', ['order' => $order, 'history' => $history]) ?>
            </div>

            <div class="panel">
                <h2 class="panel__title">Items</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Product</th><th scope="col" class="num">Price</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($item['product_slug'] && (int) $item['product_active'] === 1): ?>
                                            <a href="<?= e(url('product.php', ['slug' => $item['product_slug']])) ?>"><?= e($item['product_name']) ?></a>
                                        <?php else: ?>
                                            <?= e($item['product_name']) ?>
                                        <?php endif; ?>
                                        <?= View::partial('custom-note', ['text' => (string) ($item['customization_text'] ?? '')]) ?>
                                        <?php if ($order['status'] === 'delivered' && $item['product_id'] !== null): ?>
                                            <p class="review-link">
                                                <a href="<?= e(url('account/review.php', ['product' => $item['product_id']])) ?>"><?= in_array((int) $item['product_id'], $reviewed, true) ? 'Edit your review' : '★ Write a review' ?></a>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?= e(money($item['unit_price'])) ?></td>
                                    <td class="num"><?= e((string) $item['quantity']) ?></td>
                                    <td class="num"><?= e(money($item['line_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <dl class="totals totals--wide">
                    <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                    <div><dt>Delivery</dt><dd><?= (float) $order['delivery_fee'] > 0 ? e(money($order['delivery_fee'])) : 'Free' ?></dd></div>
                    <?php if ((float) $order['discount_amount'] > 0): ?>
                        <div><dt>Discount</dt><dd>&minus;<?= e(money($order['discount_amount'])) ?></dd></div>
                    <?php endif; ?>
                    <div class="totals__grand"><dt>Total</dt><dd><?= e(money($order['total_amount'])) ?></dd></div>
                </dl>
            </div>

            <div class="two-col">
                <div class="panel">
                    <h2 class="panel__title">Delivery</h2>
                    <p>
                        <strong><?= e($order['recipient_name']) ?></strong><br>
                        <?= e($order['recipient_phone']) ?><br>
                        <?= e($order['address_line1']) ?><br>
                        <?php if (!empty($order['address_line2'])): ?><?= e($order['address_line2']) ?><br><?php endif; ?>
                        <?= e($order['city']) ?>, <?= e($order['district']) ?> <?= e($order['postal_code'] ?? '') ?>
                    </p>
                    <?php if (!empty($order['delivery_notes'])): ?>
                        <p class="muted pre-line"><strong>Notes:</strong> <?= e($order['delivery_notes']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <h2 class="panel__title">Payment</h2>
                    <p><?= e(PaymentService::METHODS[$payment['method']] ?? '') ?> &middot; <?= payment_status_badge($payment['status'] ?? null) ?></p>
                    <p class="muted">Amount: <?= e(money($payment['amount'] ?? $order['total_amount'])) ?></p>
                    <?php if (!empty($payment['reference'])): ?>
                        <p class="muted">Your transfer reference: <?= e($payment['reference']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($payment['paid_at'])): ?>
                        <p class="muted">Paid on <?= e(format_datetime($payment['paid_at'])) ?></p>
                    <?php endif; ?>

                    <?php if ($canSendReference): ?>
                        <?= View::partial('bank-details', ['bank' => $bank, 'order' => $order]) ?>
                        <form method="post" action="<?= e(url('account/order.php', ['order' => $order['order_number']])) ?>" class="form form--compact">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="bank_reference">
                            <div class="form-field">
                                <label for="reference"><?= !empty($payment['reference']) ? 'Update transfer reference' : 'Transfer reference from your bank slip' ?></label>
                                <input type="text" id="reference" name="reference" maxlength="100" required value="<?= e($payment['reference'] ?? '') ?>">
                            </div>
                            <button type="submit" class="btn btn--primary btn--small">Send reference</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($canCancel): ?>
                <div class="panel panel--danger">
                    <h2 class="panel__title">Cancel this order</h2>
                    <p class="muted">You can cancel while the order is still pending. The items go back into stock.</p>
                    <form method="post" action="<?= e(url('account/order.php', ['order' => $order['order_number']])) ?>" class="form form--compact"
                          data-confirm="Cancel order <?= e($order['order_number']) ?>? This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <div class="form-field">
                            <label for="reason">Reason <span class="optional">(optional)</span></label>
                            <input type="text" id="reason" name="reason" maxlength="300">
                        </div>
                        <button type="submit" class="btn btn--danger btn--small">Cancel order</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
