<?php
/**
 * E-mail layout. Variables: $subject, $content.
 * E-mail programs ignore stylesheets, so this layout uses inline styles
 * (it is never served as a web page, so the site's CSP does not apply).
 */
$store = setting('store_name', 'GiftAura');
$contact = array_filter([setting('store_email'), setting('store_phone')]);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f1ee;font-family:Arial,Helvetica,sans-serif;color:#2b2b2b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f1ee;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background:#8a3d5c;color:#ffffff;padding:18px 24px;font-size:22px;font-weight:bold;"><?= e($store) ?></td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.55;">
<?= $content ?>
</td></tr>
<tr><td style="padding:16px 24px;background:#faf7f5;color:#777777;font-size:12px;line-height:1.5;">
<div>This e-mail was sent by <?= e($store) ?> because of activity on your account.</div>
<?php if ($contact !== []): ?><div>Questions? Contact us: <?= e(implode(' · ', $contact)) ?></div><?php endif; ?>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
