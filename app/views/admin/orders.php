<?php
/** Admin orders list. Variables: $filters, $counts, $result. */
$base = array_diff_key($filters, ['status' => 1]);
?>
<nav class="tabs" aria-label="Filter by status">
    <a class="tabs__tab<?= !isset($filters['status']) ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php', $base)) ?>">All <span><?= e((string) array_sum($counts)) ?></span></a>
    <?php foreach (OrderService::STATUSES as $key => $label): ?>
        <a class="tabs__tab<?= ($filters['status'] ?? '') === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php', ['status' => $key] + $base)) ?>"><?= e($label) ?> <span><?= e((string) $counts[$key]) ?></span></a>
    <?php endforeach; ?>
</nav>

<form method="get" action="<?= e(url('admin/orders.php')) ?>" class="filters filters--panel">
    <?php if (isset($filters['status'])): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <label class="sr-only" for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Order no., name, email or phone" maxlength="100">
    <label class="sr-only" for="payment">Payment status</label>
    <select id="payment" name="payment">
        <option value="">Any payment status</option>
        <?php foreach (PaymentService::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($filters['payment'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="method">Payment method</label>
    <select id="method" name="method">
        <option value="">Any method</option>
        <?php foreach (PaymentService::METHODS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($filters['method'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="from">From</label>
    <input type="date" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <label for="to">To</label>
    <input type="date" id="to" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/orders.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title"><?= e((string) $result['total']) ?> order<?= $result['total'] === 1 ? '' : 's' ?></h2>
    </header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No orders match these filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="num">Items</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/order.php', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number']) ?></strong></a><br><small class="muted"><?= e(format_datetime($o['created_at'])) ?></small></td>
                        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['customer_phone']) ?> · <?= e($o['district']) ?></small></td>
                        <td class="num"><?= e((string) (int) $o['units']) ?><?php if ((int) ($o['custom_items'] ?? 0) > 0): ?><br><span class="badge badge--neutral" title="Contains a personalised item">Personalised</span><?php endif; ?></td>
                        <td><?= order_status_badge($o['status']) ?></td>
                        <td><?= e(PaymentService::METHODS[$o['payment_method']] ?? '') ?><br><?= payment_status_badge($o['payment_status']) ?></td>
                        <td class="num"><?= e(money($o['total_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/orders.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
