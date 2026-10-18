<?php /** Variables: $name, $link. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;">Reset your password</h1>
<p>Hello <?= e($name) ?>,</p>
<p>Someone (hopefully you) asked to reset the password of your account. Click the button to choose a new password.</p>
<?php $label = 'Choose a new password'; require __DIR__ . '/_button.php'; ?>
<p>This link works once and expires in 60 minutes. If you did not ask for this, ignore this e-mail — your password stays the same.</p>
