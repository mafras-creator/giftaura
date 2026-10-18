<?php
/**
 * Star rating (no inline styles, so it works with the Content-Security-Policy).
 * Variables: $rating (0-5, rounded to the nearest half star), $size ('small'|'large', optional).
 */
$value = max(0.0, min(5.0, (float) ($rating ?? 0)));
$halfSteps = (int) round($value * 2); // 0..10
?>
<span class="stars stars--<?= e((string) ($halfSteps * 10)) ?><?= ($size ?? '') !== '' ? ' stars--' . e($size) : '' ?>" role="img" aria-label="<?= e(rtrim(rtrim(number_format($value, 1), '0'), '.')) ?> out of 5 stars"><span aria-hidden="true">★★★★★</span></span>
