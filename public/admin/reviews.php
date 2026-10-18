<?php
/**
 * GiftAura - admin: product reviews.
 * GET filters: status (visible|hidden), rating (1-5), q (product or customer), page.
 * POST actions: hide, show, delete (CSRF protected).
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin();

$filters = array_filter([
    'status' => in_array(query_string('status'), ['visible', 'hidden'], true) ? query_string('status') : '',
    'rating' => preg_match('/^[1-5]$/', query_string('rating')) ? query_string('rating') : '',
    'q'      => mb_substr(query_string('q'), 0, 100),
], static fn (string $v): bool => $v !== '');

if (is_post()) {
    Csrf::verify();
    $id = ctype_digit(post_string('id')) ? (int) post_string('id') : 0;
    $done = match (post_string('action')) {
        'hide'   => ReviewService::setVisible($id, false, $admin['id']) ? 'The review is now hidden from the shop.' : null,
        'show'   => ReviewService::setVisible($id, true, $admin['id']) ? 'The review is shown on the product page again.' : null,
        'delete' => ReviewService::delete($id, $admin['id']) ? 'The review was deleted.' : null,
        default  => abort(400, 'Unknown action.'),
    };
    Flash::add($done !== null ? 'success' : 'error', $done ?? 'Review not found.');
    $back = $filters;
    if (ctype_digit(query_string('page'))) {
        $back['page'] = query_string('page');
    }
    redirect('admin/reviews.php', $back);
}

View::render('admin/reviews', [
    'title'   => 'Reviews',
    'filters' => $filters,
    'result'  => ReviewService::adminList($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
]);
