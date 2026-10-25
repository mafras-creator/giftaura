<?php
/** GiftAura - admin dashboard: live sales, orders, customers and stock figures. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireAdmin();

View::render('admin/dashboard', [
    'title'    => 'Dashboard',
    'user'     => $user,
    'summary'  => DashboardService::summary(),
    'lowStock' => DashboardService::lowStockProducts(),
    'recent'   => DashboardService::recentOrders(),
    'newInquiries' => InquiryService::countNew(),
    'shopToday' => ShopSaleService::totals(date('Y-m-d'), date('Y-m-d')),
]);
