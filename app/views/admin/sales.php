<?php
/** Admin - in-shop sales list. Variables: $filters, $result, $today, $month. */
?>
<div class="page-actions">
    <p class="admin-lead">Sales to customers who visit the shop. Each sale reduces the stock of the products sold and has a printable invoice.</p>
    <a class="btn btn--primary" href="<?= e(url('admin/sale-new.php')) ?>">+ New in-shop sale</a>
</div>

<section class="stat-grid stat-grid--small" aria-label="In-shop sales summary">
    <div class="stat-card"><h2 class="stat-card__label">Shop sales today</h2><p class="stat-card__value"><?= e(money($today['amount'])) ?></p><p class="stat-card__note"><?= e((string) $today['count']) ?> invoice(s)</p></div>
    <div class="stat-card"><h2 class="stat-card__label">Shop sales this month</h2><p class="stat-card__value"><?= e(money($month['amount'])) ?></p><p class="stat-card__note"><?= e((string) $month['count']) ?> invoice(s)</p></div>
</section>

<form method="get" action="<?= e(url('admin/sales.php')) ?>" class="filters filters--panel">
    <label class="sr-only" for="q">Invoice or customer</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Invoice no., name or phone" maxlength="60">
    <label for="from">From</label>
    <input type="date" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <label for="to">To</label>
    <input type="date" id="to" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">All invoices</option>
        <option value="completed"<?= ($filters['status'] ?? '') === 'completed' ? ' selected' : '' ?>>Completed</option>
        <option value="voided"<?= ($filters['status'] ?? '') === 'voided' ? ' selected' : '' ?>>Cancelled</option>
    </select>
    <button type="submit" class="btn btn--ghost">Filter</button>
    <?php if ($filters !== []): ?><a href="<?= e(url('admin/sales.php')) ?>">Clear</a><?php endif; ?>
</form>

<section class="admin-panel">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title"><?= e((string) $result['total']) ?> invoice<?= $result['total'] === 1 ? '' : 's' ?></h2>
        <span class="muted">Completed: <?= e(money($result['amount'])) ?></span>
    </header>
    <?php if ($result['items'] === []): ?>
        <p class="empty-state">No in-shop sales<?= $filters !== [] ? ' match these filters' : ' yet. Press “New in-shop sale” when a customer buys in the shop' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">Invoice</th><th scope="col">Date</th><th scope="col">Customer</th><th scope="col" class="num">Items</th><th scope="col">Payment</th><th scope="col" class="num">Total</th><th scope="col">Status</th><th scope="col">Sold by</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $s): ?>
                    <tr<?= $s['status'] === 'voided' ? ' class="is-voided"' : '' ?>>
                        <td><a href="<?= e(url('admin/sale.php', ['id' => $s['id']])) ?>"><strong><?= e($s['invoice_number']) ?></strong></a></td>
                        <td><?= e(format_datetime($s['created_at'])) ?></td>
                        <td><?= e($s['customer_name'] ?? 'Walk-in customer') ?><?= $s['customer_phone'] !== null ? '<br><small class="muted">' . e($s['customer_phone']) . '</small>' : '' ?></td>
                        <td class="num"><?= e((string) $s['units']) ?></td>
                        <td><?= e(ShopSaleService::PAYMENT_METHODS[$s['payment_method']] ?? $s['payment_method']) ?></td>
                        <td class="num"><?= e(money($s['total'])) ?></td>
                        <td><span class="badge badge--<?= $s['status'] === 'completed' ? 'success' : 'danger' ?>"><?= $s['status'] === 'completed' ? 'Completed' : 'Cancelled' ?></span></td>
                        <td><?= e($s['cashier_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'path' => 'admin/sales.php', 'params' => $filters]) ?>
    <?php endif; ?>
</section>
