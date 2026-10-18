<?php
/**
 * GiftAura - small global helper functions used by pages and templates.
 */
declare(strict_types=1);

/** Read a configuration value with dot notation, e.g. config('db.port'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['GA_CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape a value for safe HTML output (prevents XSS). Use for EVERY printed value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site base path without trailing slash, e.g. "/giftaura". */
function base_path(): string
{
    return rtrim((string) config('app.base_url', ''), '/');
}

/**
 * Build an internal URL. url('login.php', ['next' => '/x']) -> /giftaura/login.php?next=%2Fx
 */
function url(string $path = '', array $query = []): string
{
    $result = base_path() . '/' . ltrim($path, '/');
    if ($query !== []) {
        $result .= '?' . http_build_query($query);
    }
    return $result;
}

/**
 * Full URL for links inside e-mails, e.g.
 * absolute_url('verify-email.php', ['token' => 'x']) -> http://localhost/giftaura/verify-email.php?token=x
 */
function absolute_url(string $path = '', array $query = []): string
{
    return rtrim((string) config('app.site_url', 'http://localhost'), '/') . url($path, $query);
}

/** URL of a file in public/assets with a version stamp so browsers reload changed files. */
function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

/** Format an LKR amount: money(4500) -> "Rs. 4,500.00". */
function money(string|int|float|null $amount): string
{
    return 'Rs. ' . number_format((float) ($amount ?? 0), 2, '.', ',');
}

/** Format a database DATETIME for display: "16 Oct 2026, 8:45 pm". */
function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time === false ? '' : date('j M Y, g:i a', $time);
}

/** Redirect to an internal path and stop. */
function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

/**
 * Accept a "next" target only if it is a path inside this site.
 * Prevents open-redirect attacks such as ?next=https://evil.example
 */
function safe_next(?string $next, string $fallback = ''): string
{
    $next = (string) $next;
    $base = base_path() . '/';
    if ($next !== ''
        && str_starts_with($next, $base)
        && !str_contains($next, '//')
        && !str_contains($next, '\\')
        && !preg_match('/[\r\n]/', $next)) {
        return $next;
    }
    return url($fallback);
}

/** True when the current request is a POST. */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a trimmed string from POST data (never an array). */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Read a trimmed string from the query string (never an array). */
function query_string(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Client IP address (direct connection only; proxy headers are not trusted). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Hidden CSRF input for forms. */
function csrf_field(): string
{
    return Csrf::field();
}

/** Logged-in user row or null. */
function current_user(): ?array
{
    return Auth::user();
}

/** Store setting (from the settings table). */
function setting(string $key, string $default = ''): string
{
    return SettingsService::get($key, $default);
}

/** Stop and show an error page (403, 404, 419, 500). */
function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    $titles = [
        400 => 'Bad request',
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Page expired',
        500 => 'Something went wrong',
    ];
    $template = in_array($status, [403, 404, 500], true) ? 'errors/' . $status : 'errors/generic';
    View::render($template, [
        'title'   => $titles[$status] ?? 'Error',
        'status'  => $status,
        'message' => $message,
        'layout'  => 'error',
    ]);
    exit;
}

/**
 * URL of a product image, or the placeholder when there is no image or
 * the stored name does not match the generated pattern.
 */
function product_image_url(?string $fileName): string
{
    if ($fileName !== null && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $fileName)
        && is_file(APP_ROOT . '/public/uploads/products/' . $fileName)) {
        return url('uploads/products/' . $fileName);
    }
    return asset('img/placeholder-product.svg');
}

/**
 * Availability of a product for customers:
 * ['key' => in_stock|low_stock|out_of_stock, 'label' => text shown].
 */
function stock_status(array $product): array
{
    $stock = (int) ($product['stock_quantity'] ?? 0);
    $threshold = (int) ($product['low_stock_threshold'] ?? 0);
    if ($stock <= 0) {
        return ['key' => 'out_of_stock', 'label' => 'Out of stock'];
    }
    if ($stock <= $threshold) {
        return ['key' => 'low_stock', 'label' => 'Only ' . $stock . ' left'];
    }
    return ['key' => 'in_stock', 'label' => 'In stock'];
}

/**
 * Make a URL slug: "Red Rose Bouquet!" -> "red-rose-bouquet".
 * Returns '' if nothing usable remains (e.g. a name written only in Sinhala);
 * callers then ask for a slug or generate one.
 */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = strtolower($converted);
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim(substr($text, 0, 150), '-');
}

/** Coloured badge for an order status. */
function order_status_badge(string $status): string
{
    $label = OrderService::STATUSES[$status] ?? $status;
    return '<span class="badge badge--status-' . e($status) . '">' . e($label) . '</span>';
}

/** Coloured badge for a payment status. */
function payment_status_badge(?string $status): string
{
    $status = $status ?? 'pending';
    $label = PaymentService::STATUSES[$status] ?? $status;
    return '<span class="badge badge--pay-' . e($status) . '">' . e($label) . '</span>';
}

/** Error message under a form field (escaped). Empty string when no error. */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

/** Extra attributes for an input that has an error (red border + screen reader link). */
function field_attrs(array $errors, string $field): string
{
    return isset($errors[$field])
        ? ' class="is-invalid" aria-invalid="true" aria-describedby="' . e($field) . '-error"'
        : '';
}

/**
 * Mark a navigation link as active. $page is relative to public/,
 * e.g. nav_active('index.php') or nav_active('admin/index.php').
 */
function nav_active(string $page): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $publicDir = str_replace('\\', '/', APP_ROOT) . '/public/';
    // Case-insensitive because Windows paths may differ in drive-letter case.
    return strcasecmp($script, $publicDir . ltrim($page, '/')) === 0 ? ' is-active' : '';
}

/**
 * Sale details of a product row: ['was' => '2000.00', 'percent' => 25]
 * when the price before the discount is higher than the price, otherwise null.
 */
function sale_info(array $product): ?array
{
    $was = $product['compare_at_price'] ?? null;
    if ($was === null || $was === '') {
        return null;
    }
    $wasCents = CartService::toCents((string) $was);
    $nowCents = CartService::toCents((string) $product['price']);
    if ($wasCents <= $nowCents || $wasCents <= 0) {
        return null;
    }
    return ['was' => (string) $was, 'percent' => max(1, (int) floor(($wasCents - $nowCents) * 100 / $wasCents))];
}
