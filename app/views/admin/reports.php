<?php
/** Reports. Variables: $report, $from, $to, $notice, $data. */
$dated = !in_array($report, ['inventory', 'low_stock'], true);
$exportUrl = url('admin/report-export.php', $dated ? ['report' => $report, 'from' => $from, 'to' => $to] : ['report' => $report]);
$quick = [
    'This month' => [date('Y-m-01'), date('Y-m-d')],
    'Last month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Last 7 days' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'Today' => [date('Y-m-d'), date('Y-m-d')],
];
?>
<nav class="tabs" aria-label="Reports">
    <?php foreach (ReportService::REPORTS as $key => $label): ?>
        <a class="tabs__tab<?= $key === $report ? ' is-active' : '' ?>" href="<?= e(url('admin/reports.php', ['report' => $key, 'from' => $from, 'to' => $to])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="toolbar">
    <?php if ($dated): ?>
        <form method="get" action="<?= e(url('admin/reports.php')) ?>" class="filters">
            <input type="hidden" name="report" value="<?= e($report) ?>">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>" required>
            <button type="submit" class="btn btn--ghost">Show</button>
            <span class="quick-ranges">
                <?php foreach ($quick as $label => [$qf, $qt]): ?>
                    <a href="<?= e(url('admin/reports.php', ['report' => $report, 'from' => $qf, 'to' => $qt])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </span>
        </form>
    <?php else: ?>
        <p class="muted">Stock levels right now (<?= e(format_datetime(date('Y-m-d H:i:s'))) ?>).</p>
    <?php endif; ?>
    <a class="btn btn--primary" href="<?= e($exportUrl) ?>">Export CSV</a>
</div>

<?php if ($notice): ?><div class="alert alert--warning"><span><?= e($notice) ?></span></div><?php endif; ?>

<?php if ($report === 'sales'): $s = $data['summary']; ?>
    <section class="stat-grid">
        <article class="stat-card"><h2 class="stat-card__label">Gross sales</h2><p class="stat-card__value"><?= e(money($s['gross_sales'])) ?></p><p class="stat-card__note"><?= e((string) $s['orders_counted']) ?> orders counted</p></article>
        <article class="stat-card"><h2 class="stat-card__label">Collected revenue</h2><p class="stat-card__value"><?= e(money($s['collected'])) ?></p><p class="stat-card__note">Payments marked paid in this period</p></article>
        <article class="stat-card"><h2 class="stat-card__label">Average order</h2><p class="stat-card__value"><?= e(money($s['average_order'])) ?></p><p class="stat-card__note">Gross sales ÷ orders counted</p></article>
        <article class="stat-card stat-card--warning"><h2 class="stat-card__label">Still pending</h2><p class="stat-card__value"><?= e(money($s['pending_value'])) ?></p><p class="stat-card__note">Included in gross sales</p></article>
        <article class="stat-card"><h2 class="stat-card__label">All orders placed</h2><p class="stat-card__value"><?= e((string) $s['orders_all']) ?></p><p class="stat-card__note">Including <?= e((string) $s['cancelled']) ?> cancelled</p></article>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Gross sales per day</h2></header>
        <?php
        $max = max(array_map(static fn($d) => (float) $d['sales'], $data['days']) ?: [0]);
        $count = count($data['days']);
        $barW = $count > 0 ? max(4, (int) floor(880 / $count) - 4) : 10;
        ?>
        <?php if ($max <= 0): ?>
            <p class="empty-state">No counted sales in this period.</p>
        <?php else: ?>
            <div class="chart-wrap">
                <svg class="bar-chart" viewBox="0 0 900 220" role="img" aria-label="Gross sales per day from <?= e($from) ?> to <?= e($to) ?>">
                    <line x1="10" y1="190" x2="890" y2="190" class="bar-chart__axis"/>
                    <?php foreach ($data['days'] as $i => $d): ?>
                        <?php $h = $max > 0 ? (int) round(((float) $d['sales'] / $max) * 170) : 0; $x = 10 + $i * ($barW + 4); ?>
                        <rect x="<?= e((string) $x) ?>" y="<?= e((string) (190 - $h)) ?>" width="<?= e((string) $barW) ?>" height="<?= e((string) max($h, 0)) ?>" class="bar-chart__bar" rx="2">
                            <title><?= e($d['day']) ?>: <?= e(money($d['sales'])) ?> (<?= e((string) $d['orders']) ?> orders)</title>
                        </rect>
                    <?php endforeach; ?>
                    <text x="10" y="210" class="bar-chart__label"><?= e($from) ?></text>
                    <text x="890" y="210" text-anchor="end" class="bar-chart__label"><?= e($to) ?></text>
                    <text x="10" y="14" class="bar-chart__label">Highest day: <?= e(money($max)) ?></text>
                </svg>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">Date</th><th scope="col" class="num">Orders counted</th><th scope="col" class="num">Gross sales</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($data['days']) as $d): ?>
                    <?php if ($d['orders'] > 0): ?>
                        <tr><td><?= e($d['day']) ?></td><td class="num"><?= e((string) $d['orders']) ?></td><td class="num"><?= e(money($d['sales'])) ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="total-row"><th>Total</th><td class="num"><?= e((string) $s['orders_counted']) ?></td><td class="num"><?= e(money($s['gross_sales'])) ?></td></tr></tfoot>
            </table>
        </div>
    </section>

<?php elseif ($report === 'orders'): ?>
    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title"><?= e((string) count($data['rows'])) ?> orders (all statuses)</h2></header>
        <?php if ($data['rows'] === []): ?>
            <p class="empty-state">No orders in this period.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['rows'] as $o): ?>
                        <tr>
                            <td><?= e($o['order_number']) ?></td>
                            <td><?= e(format_datetime($o['created_at'])) ?></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><?= e(PaymentService::METHODS[$o['payment_method']] ?? '') ?> · <?= payment_status_badge($o['payment_status']) ?></td>
                            <td class="num"><?= e(money($o['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($report === 'status'): ?>
    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Orders by current status</h2></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Status</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Value</th></tr></thead>
                <tbody>
                <?php foreach ($data['rows'] as $r): ?>
                    <tr><td><?= order_status_badge($r['status']) ?></td><td class="num"><?= e((string) $r['orders']) ?></td><td class="num"><?= e(money($r['value'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="total-row"><th>All</th><td class="num"><?= e((string) array_sum(array_column($data['rows'], 'orders'))) ?></td><td class="num"><?= e(money(array_sum(array_map('floatval', array_column($data['rows'], 'value'))))) ?></td></tr></tfoot>
            </table>
        </div>
        <p class="panel-note">This table includes cancelled orders so that every order is accounted for. Sales figures elsewhere exclude them.</p>
    </section>

<?php elseif ($report === 'products'): ?>
    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Top 10 products by quantity sold</h2></header>
        <?php if ($data['rows'] === []): ?>
            <p class="empty-state">No products sold in this period.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">#</th><th scope="col">Product</th><th scope="col" class="num">Quantity</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['rows'] as $i => $r): ?>
                        <tr><td><?= e((string) ($i + 1)) ?></td><td><?= e($r['product_name']) ?><br><small class="muted"><?= e($r['product_sku'] ?? '') ?></small></td>
                            <td class="num"><?= e((string) $r['quantity']) ?></td><td class="num"><?= e((string) $r['orders']) ?></td><td class="num"><?= e(money($r['revenue'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="panel-note">Counts orders that are not cancelled and whose payment is not refunded.</p>
    </section>

<?php else: ?>
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title"><?= $report === 'low_stock' ? 'Products at or below their alert level' : 'All products' ?></h2>
            <span class="muted">Stock value: <?= e(money(array_sum(array_map(static fn($r) => (float) $r['stock_value'], $data['rows'])))) ?></span>
        </header>
        <?php if ($data['rows'] === []): ?>
            <p class="empty-state">Nothing to show.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col" class="num">Price</th><th scope="col" class="num">Stock</th><th scope="col" class="num">Alert at</th><th scope="col" class="num">Stock value</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['rows'] as $r): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/product-edit.php', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><br><small class="muted"><?= e($r['sku'] ?? '') ?></small></td>
                            <td><?= e($r['category_name']) ?></td>
                            <td class="num"><?= e(money($r['price'])) ?></td>
                            <td class="num"><?= e((string) $r['stock_quantity']) ?></td>
                            <td class="num"><?= e((string) $r['low_stock_threshold']) ?></td>
                            <td class="num"><?= e(money($r['stock_value'])) ?></td>
                            <td><?= e($r['state']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<details class="admin-panel definitions">
    <summary>How these figures are calculated</summary>
    <ul>
        <li><strong>Gross sales</strong>: total of orders placed in the period that are not cancelled and whose payment is not refunded. Each order is counted once.</li>
        <li><strong>Collected revenue</strong>: payments an administrator marked as paid, by the date they were marked paid.</li>
        <li><strong>Still pending</strong>: orders in gross sales whose status is still "Pending".</li>
        <li><strong>Dates</strong> include both the start and the end day, in Sri Lanka time.</li>
    </ul>
</details>
