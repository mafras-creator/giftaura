<?php /** Variables: $name, $link. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;">Please verify your e-mail address</h1>
<p>Hello <?= e($name) ?>,</p>
<p>Thank you for creating an account. Please confirm that this is your e-mail address so that we can send you order updates. You need a verified address to place an order.</p>
<?php $label = 'Verify my e-mail address'; require __DIR__ . '/_button.php'; ?>
<p>This link works once and expires in 24 hours. If you did not create an account, you can ignore this e-mail.</p>
