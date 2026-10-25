<?php
/** Admin dashboard. Variables: $user, $summary, $lowStock, $recent, $newInquiries, $shopToday. Staff see only their own areas. */
$cards = [
    ['Sales today',       money($summary['sales_today']),  $summary['orders_today'] . ' order(s) placed today', '', 'admin/reports.php', ['report' => 'sales', 'from' => date('Y-m-d'), 'to' => date('Y-m-d')]],
    ['Sales this month',  money($summary['sales_month']),  $summary['orders_month'] . ' order(s) · collected ' . money($summary['collected_month']), '', 'admin/reports.php', ['report' => 'sales']],
    ['Pending orders',    number_format($summary['pending_orders']), 'Waiting for confirmation', $summary['pending_orders'] > 0 ? 'stat-card--warning' : '', 'admin/orders.php', ['status' => 'pending']],
    ['Orders in progress', number_format($summary['open_orders']), 'Confirmed, processing or ready', '', 'admin/orders.php', []],
    ['Payments to verify', number_format($summary['awaiting_payments']), 'Bank transfers with a reference', $summary['awaiting_payments'] > 0 ? 'stat-card--warning' : '', 'admin/orders.php', ['payment' => 'awaiting_verification']],
    ['Customers',         number_format($summary['customers']), 'Active customer accounts', '', 'admin/customers.php', []],
    ['Active products',   number_format($summary['active_products']), 'Inactive: ' . $summary['inactive_products'] . ' · Categories: ' . $summary['active_categories'], '', 'admin/products.php', []],
    ['Low / out of stock', $summary['low_stock'] . ' / ' . $summary['out_of_stock'], 'Active products needing stock', ($summary['low_stock'] + $summary['out_of_stock']) > 0 ? 'stat-card--danger' : '', 'admin/inventory.php', []],
    ['Shop sales today',  money($shopToday['amount']), $shopToday['count'] . ' invoice(s) · New in-shop sale', '', 'admin/sales.php', []],
    ['New inquiries',     number_format($newInquiries), 'Messages from the Contact page', $newInquiries > 0 ? 'stat-card--warning' : '', 'admin/inquiries.php', ['status' => 'new']],
];
// Each card belongs to an admin area; staff members only see the areas they were given.
$cardArea = ['Sales today' => 'reports', 'Sales this month' => 'reports', 'Pending orders' => 'orders', 'Orders in progress' => 'orders',
    'Payments to verify' => 'orders', 'Customers' => 'customers', 'Active products' => 'products', 'Low / out of stock' => 'products',
    'New inquiries' => 'inquiries', 'Shop sales today' => 'shop_sales'];
$cards = array_values(array_filter($cards, static fn($c) => Auth::can($cardArea[$c[0]])));
?>
<?php $failedEmails = Auth::isSuperAdmin() ? EmailService::failedCount() : 0; if ($failedEmails > 0): ?>
    <div class="alert alert--error"><span><?= e((string) $failedEmails) ?> e-mail(s) could not be sent in the last 7 days.
        <a href="<?= e(url('admin/emails.php', ['status' => 'failed'])) ?>">Open the e-mail log</a></span></div>
<?php endif; ?>
<?php if (Auth::can('reports')): ?><p class="admin-lead">All figures are read live from the database. Sales exclude cancelled orders and refunded payments.</p><?php endif; ?>

<section class="stat-grid" aria-label="Store summary">
    <?php foreach ($cards as [$label, $value, $note, $modifier, $path, $query]): ?>
        <a class="stat-card stat-card--link <?= e($modifier) ?>" href="<?= e(url($path, $query)) ?>">
            <h2 class="stat-card__label"><?= e($label) ?></h2>
            <p class="stat-card__value"><?= e($value) ?></p>
            <p class="stat-card__note"><?= e($note) ?></p>
        </a>
    <?php endforeach; ?>
</section>

<?php if ($user['role'] === 'staff'): ?>
    <div class="alert alert--info"><span>Welcome, <?= e($user['full_name']) ?>. Your staff account can work in:
        <?= e(implode(', ', array_map(static fn($p) => StaffService::PERMISSIONS[$p][0], $user['permission_list']))) ?>.</span></div>
<?php endif; ?>

<div class="admin-columns">
    <?php if (Auth::can('orders')): ?>
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title">Latest orders</h2>
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin/orders.php')) ?>">All orders</a>
        </header>
        <?php if ($recent === []): ?>
            <p class="empty-state">No orders yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $o): ?>
                            <tr>
                                <td><a href="<?= e(url('admin/order.php', ['id' => $o['id']])) ?>"><?= e($o['order_number']) ?></a><br><small class="muted"><?= e(format_datetime($o['created_at'])) ?></small></td>
                                <td><?= e($o['customer_name']) ?></td>
                                <td><?= order_status_badge($o['status']) ?></td>
                                <td><?= payment_status_badge($o['payment_status']) ?></td>
                                <td class="num"><?= e(money($o['total_amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (Auth::can('products')): ?>
    <section class="admin-panel">
        <header class="admin-panel__header">
            <h2 class="admin-panel__title">Low-stock alerts</h2>
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin/inventory.php')) ?>">Inventory</a>
        </header>
        <?php if ($lowStock === []): ?>
            <p class="empty-state">All active products are above their low-stock level.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="num">In stock</th><th scope="col" class="num">Alert at</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStock as $row): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/product-edit.php', ['id' => $row['id']])) ?>"><?= e($row['name']) ?></a><br><small class="muted"><?= e($row['sku'] ?? '') ?> · <?= e($row['category_name']) ?></small></td>
                            <td class="num"><?= e((string) $row['stock_quantity']) ?></td>
                            <td class="num"><?= e((string) $row['low_stock_threshold']) ?></td>
                            <td><?= (int) $row['stock_quantity'] === 0 ? '<span class="badge badge--danger">Out of stock</span>' : '<span class="badge badge--warning">Low stock</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
