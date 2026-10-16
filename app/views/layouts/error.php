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
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/shop.css')) ?>">
</head>
<body class="error-body">
<main class="error-page">
    <a class="brand brand--center" href="<?= e(url()) ?>">
        <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="48" height="48">
        <span class="brand__name">GiftAura</span>
    </a>
    <?= $content ?>
</main>
</body>
</html>
