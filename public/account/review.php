<?php
/**
 * GiftAura - My account: write, edit or delete a review.
 * GET  ?product=ID  shows the form (only for a product delivered to this customer).
 * POST action=save|delete (CSRF protected).
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$productId = ctype_digit(query_string('product')) ? (int) query_string('product') : 0;
$product = $productId > 0 ? Database::fetchOne(
    'SELECT p.id, p.name, p.slug, p.is_active,
            (SELECT pi.file_name FROM product_images pi WHERE pi.product_id = p.id
              ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
       FROM products p WHERE p.id = :id',
    ['id' => $productId]
) : null;
if ($product === null || $user['role'] !== 'customer' || ReviewService::deliveredOrderId($user['id'], $productId) === null) {
    abort(404, 'You can review a product after it has been delivered to you.');
}
$review = ReviewService::findForUser($user['id'], $productId);
$errors = [];
$old = ['rating' => $review['rating'] ?? '', 'comment' => $review['comment'] ?? ''];

if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    if ($action === 'delete') {
        ReviewService::deleteOwn($user['id'], $productId);
        Flash::add('success', 'Your review was deleted.');
        redirect('account/reviews.php');
    }
    if ($action !== 'save') {
        abort(400, 'Unknown action.');
    }
    $old = ['rating' => post_string('rating'), 'comment' => mb_substr(post_string('comment'), 0, 5000)];
    $errors = ReviewService::save($user, $productId, $old);
    if ($errors === []) {
        Flash::add('success', $review === null ? 'Thank you! Your review is now shown on the product page.' : 'Your review was updated.');
        redirect('account/reviews.php');
    }
}

View::render('shop/account/review', [
    'title'   => ($review === null ? 'Write a review' : 'Edit your review') . ' – ' . $product['name'],
    'user'    => $user,
    'product' => $product,
    'review'  => $review,
    'old'     => $old,
    'errors'  => $errors,
]);
