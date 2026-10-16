<?php
/**
 * GiftAura - admin dashboard (administrators only).
 * Phase 3: catalogue, customer and stock figures from the database.
 * Phase 6 adds sales, orders and charts.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireAdmin();

View::render('admin/dashboard', [
    'title'    => 'Dashboard',
    'user'     => $user,
    'summary'  => DashboardService::summary(),
    'lowStock' => DashboardService::lowStockProducts(),
]);
