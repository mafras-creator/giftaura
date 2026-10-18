<?php /** Variables: $order, $headline, $message, $payment, $link. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;"><?= e($headline) ?></h1>
<p>Hello <?= e($order['customer_name']) ?>,</p>
<p><?= e($message) ?></p>
<p>Order: <strong><?= e($order['order_number']) ?></strong><br>
Amount: <?= e(money($payment['amount'] ?? $order['total_amount'])) ?><br>
Payment status: <strong><?= e(PaymentService::STATUSES[$payment['status'] ?? ''] ?? '') ?></strong></p>
<?php $label = 'View my order'; require __DIR__ . '/_button.php'; ?>
