<?php /** 404 Not found. Variables: $message. */ ?>
<div class="error-card">
    <p class="error-card__code">404</p>
    <h1 class="error-card__title">Page not found</h1>
    <p class="error-card__text"><?= e($message !== '' ? $message : 'The page you are looking for does not exist or is no longer available.') ?></p>
    <a class="btn btn--primary" href="<?= e(url()) ?>">Back to the homepage</a>
</div>
