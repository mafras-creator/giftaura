<?php
/**
 * About page. Variables: $tree (categories).
 * Describes what the online shop offers and how ordering works.
 * It deliberately makes no claims about the business's history,
 * location or delivery areas: those belong in Settings once confirmed.
 */
$storeName = setting('store_name', 'GiftAura');
?>
<div class="page-head">
    <div class="container">
        <h1 class="page-title">About <?= e($storeName) ?></h1>
        <p class="page-lead"><?= e(setting('store_tagline', 'Thoughtful gifts for every occasion')) ?></p>
    </div>
</div>

<section class="section section--tight">
    <div class="container prose">
        <p>
            <?= e($storeName) ?> is a gift shop offering flower bouquets, beauty creams and gift items.
            This online store lets you browse the collection, place an order from home and follow it until it is delivered.
        </p>

        <h2>What you can find here</h2>
        <ul>
            <?php foreach ($tree as $category): ?>
                <li><a href="<?= e(url('products.php', ['category' => $category['slug']])) ?>"><?= e($category['name']) ?></a><?= $category['description'] ? ' — ' . e($category['description']) : '' ?></li>
            <?php endforeach; ?>
        </ul>

        <h2>How ordering works</h2>
        <ol>
            <li>Create an account and add the items you like to your cart.</li>
            <li>Enter the delivery address and choose cash on delivery or bank transfer.</li>
            <li>You receive an order reference straight away.</li>
            <li>Follow your order's progress in <a href="<?= e(url('account/')) ?>">My account</a>.</li>
        </ol>

        <p>Prices are shown in Sri Lankan rupees (LKR). The delivery charge and the final total are always shown before you place an order.</p>
        <p><a href="<?= e(url('contact.php')) ?>">Contact us</a> with any questions.</p>
    </div>
</section>
