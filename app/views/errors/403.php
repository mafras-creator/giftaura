<?php /** 403 Access denied. Variables: $message. */ ?>
<div class="error-card">
    <p class="error-card__code">403</p>
    <h1 class="error-card__title">Access denied</h1>
    <p class="error-card__text"><?= e($message !== '' ? $message : 'You do not have permission to view this page.') ?></p>
    <a class="btn btn--primary" href="<?= e(url()) ?>">Back to the homepage</a>
</div>
