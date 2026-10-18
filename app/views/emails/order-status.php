<?php /** Variables: $order, $status, $headline, $message, $note, $link, $reviewLink (delivered only). */ ?>
<h1 style="font-size:20px;margin:0 0 12px;"><?= e($headline) ?></h1>
<p>Hello <?= e($order['customer_name']) ?>,</p>
<p><?= e($message) ?></p>
<p>Order: <strong><?= e($order['order_number']) ?></strong><br>
New status: <strong><?= e(OrderService::STATUSES[$status] ?? $status) ?></strong><br>
Total: <?= e(money($order['total_amount'])) ?></p>
<?php if ($note !== null && $note !== ''): ?>
<p style="background:#faf7f5;padding:12px;border-radius:6px;"><strong>Note:</strong> <?= e($note) ?></p>
<?php endif; ?>
<?php $label = 'View my order'; require __DIR__ . '/_button.php'; ?>
<?php if (!empty($reviewLink)): ?>
<p style="margin-top:18px;padding:12px;background:#fff8e1;border-radius:6px;">How was your gift? <a href="<?= e($reviewLink) ?>" style="color:#5e2a8c;font-weight:bold;">Rate it and write a short review</a> — it helps other customers choose.</p>
<?php endif; ?>
