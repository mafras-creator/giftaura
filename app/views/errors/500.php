<?php /** 500 Server error. Variables: $debugMessage (only when config app.debug = true). */ ?>
<div class="error-card">
    <p class="error-card__code">500</p>
    <h1 class="error-card__title">Something went wrong</h1>
    <p class="error-card__text">Sorry, we could not complete your request. Please try again in a moment.</p>
    <?php if (!empty($debugMessage)): ?>
        <p class="error-card__debug"><strong>Debug (visible because app.debug is on):</strong> <?= e($debugMessage) ?></p>
    <?php endif; ?>
    <a class="btn btn--primary" href="<?= e(url()) ?>">Back to the homepage</a>
</div>
