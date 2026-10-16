<?php
/** Side navigation for "My account" pages. Order history is added in Phase 5. */
$items = [
    ['account/index.php', 'account/', 'Profile'],
    ['account/password.php', 'account/password.php', 'Change password'],
];
?>
<nav class="account-nav" aria-label="My account">
    <ul>
        <?php foreach ($items as [$file, $path, $label]): ?>
            <li><a class="account-nav__link<?= nav_active($file) ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
