<?php
/**
 * Admin order detail. Variables: $order, $items, $history, $payment,
 * $nextStatuses, $nextPayments, $recorder.
 */
$isBankUnpaid = ($payment['method'] ?? '') === 'bank_transfer' && ($payment['status'] ?? '') !== 'paid';
?>
<p><a href="<?= e(url('admin/orders.php')) ?>">&larr; All orders</a></p>

<div class="order-summary-bar">
    <div>
        <h2 class="order-summary-bar__number"><?= e($order['order_number']) ?></h2>
        <p class="muted">Placed <?= e(format_datetime($order['created_at'])) ?></p>
    </div>
    <div class="order-summary-bar__badges">
        <span>Order: <?= order_status_badge($order['status']) ?></span>
        <span>Payment: <?= payment_status_badge($payment['status'] ?? null) ?></span>
        <span class="order-summary-bar__total"><?= e(money($order['total_amount'])) ?></span>
    </div>
</div>

<div class="admin-columns admin-columns--wide-left">
    <div class="admin-stack">
        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Items</h2></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Product</th><th scope="col">SKU</th><th scope="col" class="num">Unit price</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Line total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php if ($item['product_id'] !== null): ?><a href="<?= e(url('admin/product-edit.php', ['id' => $item['product_id']])) ?>"><?= e($item['product_name']) ?></a><?php else: ?><?= e($item['product_name']) ?><?php endif; ?></td>
                            <td><?= e($item['product_sku'] ?? '—') ?></td>
                            <td class="num"><?= e(money($item['unit_price'])) ?></td>
                            <td class="num"><?= e((string) $item['quantity']) ?></td>
                            <td class="num"><?= e(money($item['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="4" class="num">Subtotal</th><td class="num"><?= e(money($order['subtotal'])) ?></td></tr>
                        <tr><th colspan="4" class="num">Delivery</th><td class="num"><?= e(money($order['delivery_fee'])) ?></td></tr>
                        <?php if ((float) $order['discount_amount'] > 0): ?>
                            <tr><th colspan="4" class="num">Discount</th><td class="num">−<?= e(money($order['discount_amount'])) ?></td></tr>
                        <?php endif; ?>
                        <tr class="total-row"><th colspan="4" class="num">Total</th><td class="num"><?= e(money($order['total_amount'])) ?></td></tr>
                    </tfoot>
                </table>
            </div>
            <p class="panel-note">Prices are the snapshot taken when the order was placed; later product edits do not change them.</p>
        </section>

        <div class="admin-columns">
            <section class="admin-panel">
                <header class="admin-panel__header"><h2 class="admin-panel__title">Customer</h2></header>
                <dl class="kv">
                    <div><dt>Name</dt><dd><?= e($order['customer_name']) ?></dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a></dd></div>
                    <div><dt>Email</dt><dd><?= e($order['customer_email']) ?></dd></div>
                    <div><dt>Account</dt><dd><a href="<?= e(url('admin/customer.php', ['id' => $order['user_id']])) ?>"><?= e($order['account_name']) ?></a><?= (int) $order['account_active'] === 1 ? '' : ' (inactive)' ?></dd></div>
                </dl>
            </section>
            <section class="admin-panel">
                <header class="admin-panel__header"><h2 class="admin-panel__title">Deliver to</h2></header>
                <dl class="kv">
                    <div><dt>Recipient</dt><dd><?= e($order['recipient_name']) ?></dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:<?= e($order['recipient_phone']) ?>"><?= e($order['recipient_phone']) ?></a></dd></div>
                    <div><dt>Address</dt><dd><?= e($order['address_line1']) ?><?= $order['address_line2'] ? '<br>' . e($order['address_line2']) : '' ?><br><?= e($order['city']) ?>, <?= e($order['district']) ?> <?= e($order['postal_code'] ?? '') ?></dd></div>
                    <?php if (!empty($order['delivery_notes'])): ?>
                        <div><dt>Notes</dt><dd class="pre-line"><?= e($order['delivery_notes']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>
        </div>

        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Status history</h2></header>
            <ol class="history">
                <?php foreach ($history as $h): ?>
                    <li>
                        <strong><?= e(OrderService::STATUSES[$h['to_status']] ?? $h['to_status']) ?></strong>
                        <span class="muted"><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['changed_by_role'] === 'admin' ? 'Staff' : ($h['changed_by_role'] === 'customer' ? 'Customer' : 'System')) ?></span>
                        <?php if (!empty($h['note'])): ?><br><span><?= e($h['note']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <div class="admin-stack">
        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Update order status</h2></header>
            <div class="panel-body">
                <?php if ($nextStatuses === []): ?>
                    <p class="muted">This order is <strong><?= e(OrderService::STATUSES[$order['status']]) ?></strong>. No further status changes are possible.</p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('admin/order.php', ['id' => $order['id']])) ?>" class="admin-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <div class="field">
                            <label for="status">Move from <strong><?= e(OrderService::STATUSES[$order['status']]) ?></strong> to</label>
                            <select id="status" name="status" required>
                                <?php foreach ($nextStatuses as $s): ?>
                                    <option value="<?= e($s) ?>"><?= e(OrderService::STATUSES[$s]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="status-note">Note <span class="optional">(optional; shown in the history, e.g. cancellation reason)</span></label>
                            <input type="text" id="status-note" name="note" maxlength="500">
                        </div>
                        <?php if ($order['status'] === 'pending' && $isBankUnpaid): ?>
                            <label class="check check--warning"><input type="checkbox" name="allow_unpaid" value="1"> Confirm before the bank transfer is verified</label>
                        <?php endif; ?>
                        <?php if (in_array('cancelled', $nextStatuses, true)): ?>
                            <p class="hint">Cancelling returns all items to stock automatically.</p>
                        <?php endif; ?>
                        <button type="submit" class="btn btn--primary">Update status</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Payment</h2></header>
            <div class="panel-body">
                <dl class="kv">
                    <div><dt>Method</dt><dd><?= e(PaymentService::METHODS[$payment['method'] ?? ''] ?? '—') ?></dd></div>
                    <div><dt>Status</dt><dd><?= payment_status_badge($payment['status'] ?? null) ?></dd></div>
                    <div><dt>Amount</dt><dd><?= e(money($payment['amount'] ?? 0)) ?></dd></div>
                    <?php if (!empty($payment['reference'])): ?><div><dt>Customer's reference</dt><dd><code><?= e($payment['reference']) ?></code></dd></div><?php endif; ?>
                    <?php if (!empty($payment['paid_at'])): ?><div><dt>Paid on</dt><dd><?= e(format_datetime($payment['paid_at'])) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['refunded_at'])): ?><div><dt>Refunded on</dt><dd><?= e(format_datetime($payment['refunded_at'])) ?></dd></div><?php endif; ?>
                    <?php if ($recorder): ?><div><dt>Recorded by</dt><dd><?= e($recorder) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['admin_note'])): ?><div><dt>Note</dt><dd><?= e($payment['admin_note']) ?></dd></div><?php endif; ?>
                </dl>
                <?php if ($nextPayments !== []): ?>
                    <form method="post" action="<?= e(url('admin/order.php', ['id' => $order['id']])) ?>" class="admin-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="payment">
                        <div class="field">
                            <label for="payment_status">Record payment as</label>
                            <select id="payment_status" name="payment_status" required>
                                <?php foreach ($nextPayments as $s): ?>
                                    <option value="<?= e($s) ?>"><?= e(PaymentService::STATUSES[$s]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="payment-note">Note <span class="optional">(required for refunds)</span></label>
                            <input type="text" id="payment-note" name="note" maxlength="500" placeholder="e.g. Cash collected by driver / transfer checked in bank statement">
                        </div>
                        <button type="submit" class="btn btn--primary">Save payment status</button>
                    </form>
                    <p class="hint">Only mark a payment as paid after the money has actually been received.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
