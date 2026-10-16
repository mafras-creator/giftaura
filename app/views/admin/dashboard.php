<?php
/** Admin dashboard. Variables: $summary (counts), $lowStock (rows), $user. */
$cards = [
    ['Active products',    $summary['active_products'] ?? 0,   'Inactive: ' . ($summary['inactive_products'] ?? 0), ''],
    ['Active categories',  $summary['active_categories'] ?? 0, 'Including subcategories', ''],
    ['Customers',          $summary['customers'] ?? 0,         'Active customer accounts', ''],
    ['Low stock',          $summary['low_stock'] ?? 0,         'At or below the alert level', ($summary['low_stock'] ?? 0) > 0 ? 'stat-card--warning' : ''],
    ['Out of stock',       $summary['out_of_stock'] ?? 0,      'Active products with 0 stock', ($summary['out_of_stock'] ?? 0) > 0 ? 'stat-card--danger' : ''],
];
?>
<p class="admin-lead">Welcome back, <?= e($user['full_name']) ?>. Figures below are read live from the database.</p>

<section class="stat-grid" aria-label="Store summary">
    <?php foreach ($cards as [$label, $value, $note, $modifier]): ?>
        <article class="stat-card <?= e($modifier) ?>">
            <h2 class="stat-card__label"><?= e($label) ?></h2>
            <p class="stat-card__value"><?= e(number_format((int) $value)) ?></p>
            <p class="stat-card__note"><?= e($note) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-panel">
    <header class="admin-panel__header">
        <h2 class="admin-panel__title">Low-stock alerts</h2>
    </header>
    <?php if ($lowStock === []): ?>
        <p class="empty-state">All active products are above their low-stock level.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th scope="col">Product</th><th scope="col">SKU</th><th scope="col">Category</th>
                        <th scope="col" class="num">In stock</th><th scope="col" class="num">Alert level</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lowStock as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['sku'] ?? '—') ?></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td class="num"><?= e((string) $row['stock_quantity']) ?></td>
                        <td class="num"><?= e((string) $row['low_stock_threshold']) ?></td>
                        <td>
                            <?php if ((int) $row['stock_quantity'] === 0): ?>
                                <span class="badge badge--danger">Out of stock</span>
                            <?php else: ?>
                                <span class="badge badge--warning">Low stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
