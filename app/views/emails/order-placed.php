<?php /** Variables: $order, $items, $payment, $bank, $link. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;">Thank you for your order!</h1>
<p>Hello <?= e($order['customer_name']) ?>,</p>
<p>We have received your order <strong><?= e($order['order_number']) ?></strong> on <?= e(format_datetime($order['created_at'])) ?>. We will let you know when the store confirms it.</p>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:16px 0;">
<tr style="background:#faf7f5;"><th align="left">Item</th><th align="right">Qty</th><th align="right">Total</th></tr>
<?php foreach ($items as $item): ?>
<tr style="border-bottom:1px solid #eeeeee;"><td><?= e($item['product_name']) ?><?php if (($item['customization_text'] ?? '') !== ''): ?><div style="margin-top:4px;padding:6px 8px;background:#faf7fc;border-left:3px solid #ffc629;font-size:13px;"><strong style="color:#5e2a8c;">Personalisation:</strong> <span style="white-space:pre-line;"><?= e($item['customization_text']) ?></span></div><?php endif; ?></td><td align="right"><?= (int) $item['quantity'] ?></td><td align="right"><?= e(money($item['line_total'])) ?></td></tr>
<?php endforeach; ?>
<tr><td colspan="2" align="right">Subtotal</td><td align="right"><?= e(money($order['subtotal'])) ?></td></tr>
<tr><td colspan="2" align="right">Delivery</td><td align="right"><?= e(money($order['delivery_fee'])) ?></td></tr>
<?php if ((float) $order['discount_amount'] > 0): ?>
<tr><td colspan="2" align="right">Discount</td><td align="right">−<?= e(money($order['discount_amount'])) ?></td></tr>
<?php endif; ?>
<tr><td colspan="2" align="right"><strong>Total</strong></td><td align="right"><strong><?= e(money($order['total_amount'])) ?></strong></td></tr>
</table>
<p><strong>Deliver to:</strong><br>
<?= e($order['recipient_name']) ?>, <?= e($order['recipient_phone']) ?><br>
<?= e($order['address_line1']) ?><?php if (!empty($order['address_line2'])): ?>, <?= e($order['address_line2']) ?><?php endif; ?><br>
<?= e($order['city']) ?>, <?= e($order['district']) ?><?php if (!empty($order['postal_code'])): ?> <?= e($order['postal_code']) ?><?php endif; ?></p>
<?php if ($payment !== null): ?>
<p><strong>Payment:</strong> <?= e(PaymentService::METHODS[$payment['method']] ?? $payment['method']) ?></p>
<?php if ($payment['method'] === 'bank_transfer'): ?>
<?php if ($bank['bank_account_number'] !== ''): ?>
<p style="background:#faf7f5;padding:12px;border-radius:6px;">Please transfer <strong><?= e(money($order['total_amount'])) ?></strong> to:<br>
<?= e($bank['bank_name']) ?><?php if ($bank['bank_branch'] !== ''): ?> (<?= e($bank['bank_branch']) ?>)<?php endif; ?><br>
Account name: <?= e($bank['bank_account_name']) ?><br>
Account number: <?= e($bank['bank_account_number']) ?><br>
Use <strong><?= e($order['order_number']) ?></strong> as the reference, then enter your transfer reference on your order page.</p>
<?php else: ?>
<p>The store will send you the bank details for the transfer.</p>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php $label = 'View my order'; require __DIR__ . '/_button.php'; ?>
