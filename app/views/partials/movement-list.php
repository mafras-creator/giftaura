<?php
/** Stock ledger table. Variables: $movements, $showProduct (bool). */
$reasons = [
    'initial_stock' => 'Opening stock', 'restock' => 'Restock', 'order_placed' => 'Order placed',
    'order_cancelled' => 'Order cancelled', 'manual_adjustment' => 'Correction',
    'shop_sale' => 'Sold in the shop', 'shop_sale_voided' => 'Shop invoice cancelled',
];
?>
<div class="table-wrap">
    <table class="data-table data-table--compact">
        <thead>
            <tr>
                <th scope="col">When</th>
                <?php if ($showProduct): ?><th scope="col">Product</th><?php endif; ?>
                <th scope="col">Reason</th>
                <th scope="col" class="num">Change</th>
                <th scope="col" class="num">Stock after</th>
                <th scope="col">By / note</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($movements as $m): ?>
            <tr>
                <td><?= e(format_datetime($m['created_at'])) ?></td>
                <?php if ($showProduct): ?>
                    <td><a href="<?= e(url('admin/product-edit.php', ['id' => $m['product_id']])) ?>"><?= e($m['product_name']) ?></a></td>
                <?php endif; ?>
                <td>
                    <?= e($reasons[$m['reason']] ?? $m['reason']) ?>
                    <?php if (!empty($m['order_number'])): ?>
                        <br><a href="<?= e(url('admin/order.php', ['id' => $m['order_id']])) ?>"><small><?= e($m['order_number']) ?></small></a>
                    <?php endif; ?>
                    <?php if (!empty($m['invoice_number'])): ?>
                        <br><?php if (Auth::can('shop_sales')): ?><a href="<?= e(url('admin/sale.php', ['id' => $m['shop_sale_id']])) ?>"><small><?= e($m['invoice_number']) ?></small></a><?php else: ?><small><?= e($m['invoice_number']) ?></small><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="num <?= (int) $m['quantity_change'] < 0 ? 'neg' : 'pos' ?>"><?= e(sprintf('%+d', (int) $m['quantity_change'])) ?></td>
                <td class="num"><?= e((string) $m['stock_after']) ?></td>
                <td><small><?= e($m['user_name'] ?? 'System') ?><?= !empty($m['note']) ? ' · ' . e($m['note']) : '' ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
