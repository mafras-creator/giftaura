<?php
/** Personalisation message of an order or cart line. Variables: $text, $label (optional). */
if (($text ?? '') === '' || $text === null) {
    return;
}
?>
<div class="custom-note">
    <span class="custom-note__label"><?= e(($label ?? '') !== '' ? $label : 'Personalisation') ?></span>
    <span class="custom-note__text"><?= e($text) ?></span>
</div>
