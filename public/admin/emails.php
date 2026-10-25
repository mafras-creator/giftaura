<?php
/** GiftAura - admin: e-mail log (every e-mail the shop tried to send). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireSuperAdmin();

$filters = array_filter([
    'status'   => query_string('status'),
    'template' => query_string('template'),
    'q'        => mb_substr(query_string('q'), 0, 100),
], static fn (string $v): bool => $v !== '');

View::render('admin/emails', [
    'title'     => 'E-mail log',
    'filters'   => $filters,
    'transport' => Mailer::transport(),
    'result'    => EmailService::log($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
]);
