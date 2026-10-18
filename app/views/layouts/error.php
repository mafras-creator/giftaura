<?php
/**
 * Minimal layout for error pages. It does NOT use the database, so it
 * still works when the database is unavailable.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? 'Error') . ' | GiftAura') ?></title>
    <link rel="icon" href="<?= e(BrandService::logoUrl()) ?>" type="<?= e(BrandService::logoType()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/shop.css')) ?>">
</head>
<body class="error-body">
<main class="error-page">
    <a class="brand brand--center" href="<?= e(url()) ?>">
        <?php $wideLogo = BrandService::isWide(); ?>
        <img class="brand__mark<?= BrandService::hasCustomLogo() ? ' brand__mark--custom' : '' ?><?= $wideLogo ? ' brand__mark--wide' : '' ?>"
             src="<?= e(BrandService::logoUrl()) ?>" alt="<?= $wideLogo ? 'GiftAura' : '' ?>" height="48"<?= $wideLogo ? '' : ' width="48"' ?>>
        <?php if (!$wideLogo): ?><span class="brand__name">GiftAura</span><?php endif; ?>
    </a>
    <?= $content ?>
</main>
</body>
</html>
