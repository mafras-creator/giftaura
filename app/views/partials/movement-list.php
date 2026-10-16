<?php
/** Stock ledger table. Variables: $movements, $showProduct (bool). */
$reasons = [
    'initial_stock' => 'Opening stock', 'restock' => 'Restock', 'order_placed' => 'Order placed',
    'order_cancelled' => 'Order cancelled', 'manual_adjustment' => 'Correction',
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
                </td>
                <td class="num <?= (int) $m['quantity_change'] < 0 ? 'neg' : 'pos' ?>"><?= e(sprintf('%+d', (int) $m['quantity_change'])) ?></td>
                <td class="num"><?= e((string) $m['stock_after']) ?></td>
                <td><small><?= e($m['user_name'] ?? 'System') ?><?= !empty($m['note']) ? ' · ' . e($m['note']) : '' ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
