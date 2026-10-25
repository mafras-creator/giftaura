<?php /** Variables: $inquiry, $reply, $ref, $link. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;">We have answered your message</h1>
<p>Hello <?= e($inquiry['name']) ?>,</p>
<p style="background:#faf7fc;border-left:3px solid #ffc629;padding:12px;border-radius:6px;white-space:pre-line;"><?= e($reply) ?></p>
<p style="color:#675d74;font-size:13px;margin-top:18px;">Your message (<?= e($ref) ?>, <?= e(format_datetime($inquiry['created_at'])) ?>):</p>
<p style="color:#675d74;font-size:13px;white-space:pre-line;border-top:1px solid #eee;padding-top:8px;"><?= e($inquiry['message']) ?></p>
<p>If you have another question, simply reply on our Contact page.</p>
<?php $label = $inquiry['user_id'] !== null ? 'See my messages' : 'Contact us again'; require __DIR__ . '/_button.php'; ?>
