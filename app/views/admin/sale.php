<?php
/** Admin - printable invoice for an in-shop sale. Variables: $sale, $items, $voidError, $shop. */
$voided = $sale['status'] === 'voided';
$units = array_sum(array_map(static fn($i) => (int) $i['quantity'], $items));
$change = null;
if ($sale['amount_received'] !== null) {
    $change = ShopSaleService::rupees((ShopSaleService::cents((string) $sale['amount_received']) ?? 0) - (ShopSaleService::cents((string) $sale['total']) ?? 0));
}
?>
<div class="page-actions no-print">
    <a href="<?= e(url('admin/sales.php')) ?>">← All in-shop sales</a>
    <div class="actions">
        <button type="button" class="btn btn--primary" data-print>Print invoice</button>
        <a class="btn btn--ghost" href="<?= e(url('admin/sale-new.php')) ?>">+ New sale</a>
    </div>
</div>

<article class="invoice<?= $voided ? ' invoice--voided' : '' ?>" aria-label="Invoice <?= e($sale['invoice_number']) ?>">
    <?php if ($voided): ?><p class="invoice__stamp">Cancelled</p><?php endif; ?>
    <header class="invoice__header">
        <div class="invoice__shop">
            <img class="invoice__logo" src="<?= e(BrandService::logoUrl()) ?>" alt="" height="56"<?= BrandService::isWide() ? '' : ' width="56"' ?>>
            <div>
                <p class="invoice__shop-name"><?= e($shop['name']) ?></p>
                <?php if ($shop['address'] !== ''): ?><p class="invoice__shop-line"><?= nl2br(e($shop['address'])) ?></p><?php endif; ?>
                <?php if ($shop['phone'] !== '' || $shop['email'] !== ''): ?>
                    <p class="invoice__shop-line"><?= e(implode(' · ', array_filter([$shop['phone'], $shop['email']], static fn($v) => $v !== ''))) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="invoice__title">
            <h2>INVOICE</h2>
            <dl class="invoice__meta">
                <div><dt>Invoice no.</dt><dd><strong><?= e($sale['invoice_number']) ?></strong></dd></div>
                <div><dt>Date</dt><dd><?= e(format_datetime($sale['created_at'])) ?></dd></div>
                <div><dt>Served by</dt><dd><?= e($sale['cashier_name'] ?? '—') ?></dd></div>
            </dl>
        </div>
    </header>

    <div class="invoice__to">
        <p class="invoice__label">Customer</p>
        <p><?= e($sale['customer_name'] ?? 'Walk-in customer') ?><?= $sale['customer_phone'] !== null ? ' · ' . e($sale['customer_phone']) : '' ?></p>
    </div>

    <table class="invoice__items">
        <thead><tr><th scope="col">#</th><th scope="col">Item</th><th scope="col" class="num">Unit price</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $n => $item): ?>
            <tr>
                <td><?= $n + 1 ?></td>
                <td><?= e($item['product_name']) ?><?= $item['sku'] !== null && $item['sku'] !== '' ? '<br><small>' . e($item['sku']) . '</small>' : '' ?></td>
                <td class="num"><?= e(money($item['unit_price'])) ?></td>
                <td class="num"><?= e((string) $item['quantity']) ?></td>
                <td class="num"><?= e(money($item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="invoice__bottom">
        <div class="invoice__pay">
            <p><span class="invoice__label">Payment</span> <?= e(ShopSaleService::PAYMENT_METHODS[$sale['payment_method']] ?? $sale['payment_method']) ?></p>
            <?php if ($sale['note'] !== null): ?><p><span class="invoice__label">Note</span> <?= e($sale['note']) ?></p><?php endif; ?>
            <p class="invoice__units"><?= e((string) $units) ?> item<?= $units === 1 ? '' : 's' ?></p>
        </div>
        <dl class="invoice__totals">
            <div><dt>Subtotal</dt><dd><?= e(money($sale['subtotal'])) ?></dd></div>
            <?php if ((float) $sale['discount'] > 0): ?><div><dt>Discount</dt><dd>− <?= e(money($sale['discount'])) ?></dd></div><?php endif; ?>
            <div class="invoice__grand"><dt>Total</dt><dd><?= e(money($sale['total'])) ?></dd></div>
            <?php if ($sale['amount_received'] !== null): ?>
                <div><dt>Cash received</dt><dd><?= e(money($sale['amount_received'])) ?></dd></div>
                <div><dt>Change</dt><dd><?= e(money($change)) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>

    <footer class="invoice__footer">
        <p>Thank you for shopping at <?= e($shop['name']) ?>!</p>
        <?php if ($voided): ?>
            <p class="invoice__void-note">This invoice was cancelled on <?= e(format_datetime((string) $sale['voided_at'])) ?><?= $sale['voided_by_name'] !== null ? ' by ' . e($sale['voided_by_name']) : '' ?>: <?= e((string) $sale['void_reason']) ?></p>
        <?php endif; ?>
    </footer>
</article>

<?php if (!$voided): ?>
    <section class="admin-panel admin-panel--narrow void-panel no-print">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Cancel this invoice</h2></header>
        <?php if ($voidError !== null): ?><div class="alert alert--error panel-alert" role="alert"><span><?= e($voidError) ?></span></div><?php endif; ?>
        <form method="post" action="<?= e(url('admin/sale.php', ['id' => $sale['id']])) ?>" class="admin-form" data-confirm="Cancel invoice <?= e($sale['invoice_number']) ?>? The products go back into stock.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="void">
            <p class="hint">Only for a mistake or a returned item. The products go back into stock and the invoice stays in the list marked “Cancelled”.</p>
            <div class="field">
                <label for="reason">Reason</label>
                <input type="text" id="reason" name="reason" maxlength="255" required placeholder="e.g. Wrong product entered">
            </div>
            <button type="submit" class="btn btn--danger-ghost">Cancel invoice and return stock</button>
        </form>
    </section>
<?php endif; ?>
