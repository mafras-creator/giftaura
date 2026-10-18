<?php /** Button partial for e-mails. Variables: $link, $label. */ ?>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="display:inline-block;background:#8a3d5c;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;"><?= e($label) ?></a></p>
<p style="font-size:12px;color:#777777;">If the button does not work, copy this address into your browser:<br><span style="word-break:break-all;"><?= e($link) ?></span></p>
