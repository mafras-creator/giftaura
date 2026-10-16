<?php /** Generic error. Variables: $status, $title, $message. */ ?>
<div class="error-card">
    <p class="error-card__code"><?= e((string) ($status ?? 400)) ?></p>
    <h1 class="error-card__title"><?= e($title ?? 'Error') ?></h1>
    <p class="error-card__text"><?= e($message !== '' ? $message : 'The request could not be completed.') ?></p>
    <a class="btn btn--primary" href="<?= e(url()) ?>">Back to the homepage</a>
</div>
