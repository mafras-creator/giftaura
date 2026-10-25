<?php
/** GiftAura - admin: activity log (who changed what, and when). */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireSuperAdmin();

$types = ActivityLogService::entityTypes();
$type = in_array(query_string('type'), $types, true) ? query_string('type') : '';

View::render('admin/activity', [
    'title'  => 'Activity log',
    'types'  => $types,
    'type'   => $type,
    'result' => ActivityLogService::list(ctype_digit(query_string('page')) ? (int) query_string('page') : 1, $type),
]);
