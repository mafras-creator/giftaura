<?php
/** Admin - new in-shop sale. Variables: $products, $errors, $old. */
$lines = $old['lines'] ?? [];
$rows = max(4, count($lines) + 1);
$byId = [];
foreach ($products as $p) {
    $byId[(int) $p['id']] = $p;
}
?>
<p><a href="<?= e(url('admin/sales.php')) ?>">← All in-shop sales</a></p>
<p class="admin-lead">Choose the products the customer is buying. Prices are taken from the website, the stock goes down when you save,
    and an invoice with a reference number is created.</p>

<?php if ($products === []): ?>
    <div class="alert alert--warning"><span>No product has stock at the moment. Add stock in <a href="<?= e(url('admin/inventory.php')) ?>">Inventory</a> first.</span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/sale-new.php')) ?>" class="sale-form" novalidate data-sale-form>
    <?= csrf_field() ?>
    <div class="admin-columns admin-columns--wide-left">
        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Products</h2><span class="muted">Type in a list to jump to a product</span></header>
            <?php if (isset($errors['lines'])): ?><div class="alert alert--error panel-alert" role="alert"><span><?= e($errors['lines']) ?></span></div><?php endif; ?>
            <div class="table-wrap">
                <table class="data-table sale-lines">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Line total</th></tr></thead>
                    <tbody data-sale-lines>
                    <?php for ($i = 0; $i < $rows; $i++): $line = $lines[$i] ?? ['product_id' => '', 'quantity' => '']; ?>
                        <tr class="sale-line" data-sale-line>
                            <td>
                                <label class="sr-only" for="product-<?= $i ?>">Product, line <?= $i + 1 ?></label>
                                <select id="product-<?= $i ?>" name="product_id[]" data-sale-product>
                                    <option value="">— choose a product —</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= e((string) $p['id']) ?>" data-price="<?= e((string) $p['price']) ?>" data-stock="<?= e((string) $p['stock_quantity']) ?>"<?= (string) $line['product_id'] === (string) $p['id'] ? ' selected' : '' ?>>
                                            <?= e($p['name']) ?><?= $p['sku'] !== null && $p['sku'] !== '' ? ' (' . e($p['sku']) . ')' : '' ?> — <?= e(money($p['price'])) ?> · <?= e((string) $p['stock_quantity']) ?> in stock<?= (int) $p['is_active'] === 1 ? '' : ' · hidden online' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="num">
                                <label class="sr-only" for="qty-<?= $i ?>">Quantity, line <?= $i + 1 ?></label>
                                <input type="number" id="qty-<?= $i ?>" name="quantity[]" value="<?= e((string) $line['quantity']) ?>" min="1" max="<?= ShopSaleService::MAX_QTY ?>" inputmode="numeric" class="sale-line__qty" data-sale-qty>
                            </td>
                            <td class="num sale-line__total" data-sale-line-total>—</td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="sale-form__more"><button type="button" class="btn btn--ghost btn--small" data-sale-add-line hidden>+ Add another line</button></div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__header"><h2 class="admin-panel__title">Customer and payment</h2></header>
            <div class="admin-form">
                <div class="field">
                    <label for="customer_name">Customer name <span class="optional">(optional)</span></label>
                    <input type="text" id="customer_name" name="customer_name" value="<?= e($old['customer_name'] ?? '') ?>" maxlength="100" autocomplete="off">
                </div>
                <div class="field">
                    <label for="customer_phone">Customer mobile <span class="optional">(optional)</span></label>
                    <input type="tel" id="customer_phone" name="customer_phone" value="<?= e($old['customer_phone'] ?? '') ?>" maxlength="20" autocomplete="off"<?= field_attrs($errors, 'customer_phone') ?>>
                    <?= field_error($errors, 'customer_phone') ?>
                </div>
                <div class="field">
                    <label for="payment_method">Paid by</label>
                    <select id="payment_method" name="payment_method" data-sale-method<?= field_attrs($errors, 'payment_method') ?>>
                        <?php foreach (ShopSaleService::PAYMENT_METHODS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($old['payment_method'] ?? 'cash') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'payment_method') ?>
                </div>
                <div class="field">
                    <label for="discount">Discount in Rs. <span class="optional">(optional)</span></label>
                    <input type="text" id="discount" name="discount" value="<?= e($old['discount'] ?? '') ?>" inputmode="decimal" maxlength="12" placeholder="0.00" data-sale-discount<?= field_attrs($errors, 'discount') ?>>
                    <?= field_error($errors, 'discount') ?>
                </div>
                <div class="field" data-sale-cash>
                    <label for="amount_received">Cash received in Rs. <span class="optional">(optional, for the change)</span></label>
                    <input type="text" id="amount_received" name="amount_received" value="<?= e($old['amount_received'] ?? '') ?>" inputmode="decimal" maxlength="12" data-sale-received<?= field_attrs($errors, 'amount_received') ?>>
                    <?= field_error($errors, 'amount_received') ?>
                </div>
                <div class="field">
                    <label for="note">Note on the invoice <span class="optional">(optional)</span></label>
                    <input type="text" id="note" name="note" value="<?= e($old['note'] ?? '') ?>" maxlength="255" placeholder="e.g. Gift wrapped">
                </div>
                <dl class="sale-totals" aria-live="polite">
                    <div><dt>Subtotal</dt><dd data-sale-subtotal>—</dd></div>
                    <div><dt>Discount</dt><dd data-sale-discount-show>—</dd></div>
                    <div class="sale-totals__grand"><dt>Total</dt><dd data-sale-total>—</dd></div>
                    <div data-sale-change-row hidden><dt>Change</dt><dd data-sale-change>—</dd></div>
                </dl>
                <p class="hint">The totals above are a preview; the server checks prices and stock again when you save.</p>
                <button type="submit" class="btn btn--primary" data-loading-text="Saving…"<?= $products === [] ? ' disabled' : '' ?>>Save sale and create invoice</button>
            </div>
        </section>
    </div>
</form>
