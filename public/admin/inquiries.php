<?php
/**
 * GiftAura - admin: customer inquiries (messages from the Contact page).
 * GET filters: status, subject, q, page. Open one with admin/inquiry.php?id=.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin('inquiries');

$filters = array_filter([
    'status'  => isset(InquiryService::STATUSES[query_string('status')]) ? query_string('status') : '',
    'subject' => isset(InquiryService::SUBJECTS[query_string('subject')]) ? query_string('subject') : '',
    'q'       => mb_substr(query_string('q'), 0, 100),
], static fn (string $v): bool => $v !== '');

View::render('admin/inquiries', [
    'title'   => 'Customer inquiries',
    'filters' => $filters,
    'result'  => InquiryService::adminList($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
]);
