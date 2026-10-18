<?php
/**
 * GiftAura - Phase 8 system check on the REAL server (XAMPP Apache + MariaDB).
 *
 * Usage (from the giftaura folder, with Apache and MySQL started):
 *     C:\xampp\php\php.exe scripts\system_check.php
 *
 * It does NOT change your data:
 *   - database tests run inside transactions that are always rolled back;
 *   - one temporary file is written to public/uploads/products/ to prove
 *     that PHP cannot run there, and it is deleted immediately afterwards.
 * Results are printed and saved to docs/system-check-<date>.txt for the report.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$results = [];
$lines = [];
function out(string $text): void
{
    global $lines;
    $lines[] = $text;
    fwrite(STDOUT, $text . PHP_EOL);
}
function check(string $id, string $name, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = $ok;
    out(sprintf('  [%s] %-6s %s%s', $ok ? 'PASS' : 'FAIL', $id, $name, $detail !== '' ? ' — ' . $detail : ''));
}

/** HTTP GET without following redirects. Returns [status, headers(lowercase key => value), body, ms]. */
function http_get(string $url): array
{
    $context = stream_context_create(['http' => [
        'method' => 'GET', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15,
        'header' => "User-Agent: GiftAura-SystemCheck\r\n",
    ]]);
    $start = microtime(true);
    $body = @file_get_contents($url, false, $context);
    $ms = (int) round((microtime(true) - $start) * 1000);
    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
            $headers = [];
        } elseif (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return [$status, $headers, $body === false ? '' : $body, $ms];
}

$base = 'http://localhost' . base_path();
out('GiftAura system check — ' . date('Y-m-d H:i') . ' — ' . $base . '/');
out('PHP ' . PHP_VERSION . ' on ' . PHP_OS_FAMILY);
out('');

// ---------------------------------------------------------------------
out('1. Database (MariaDB)');
// ---------------------------------------------------------------------
try {
    $pdo = Database::pdo();
    $version = (string) Database::fetchValue('SELECT VERSION()');
    check('DB-01', 'Connect as ' . config('db.user') . ' on port ' . config('db.port'), true, $version);
} catch (Throwable $e) {
    check('DB-01', 'Connect to the database', false, $e->getMessage());
    out('Cannot continue without the database. Is MySQL started in XAMPP?');
    exit(1);
}

$tables = Database::query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
check('DB-02', 'All 20 tables exist', count($tables) === 20, count($tables) . ' tables'
    . (count($tables) < 20 ? ' - run the files in sql/updates/ in phpMyAdmin' : ''));

$tz = (string) Database::fetchValue('SELECT @@session.time_zone');
check('DB-03', 'Session time zone is Sri Lanka (+05:30)', $tz === '+05:30', $tz);

$grants = implode(' ', array_map(static fn($r) => (string) reset($r), Database::fetchAll('SHOW GRANTS FOR CURRENT_USER()')));
$hasDangerous = (bool) preg_match('/\b(ALL PRIVILEGES|DROP|ALTER|CREATE|GRANT OPTION)\b/i', $grants);
check('DB-04', 'App user has only SELECT/INSERT/UPDATE/DELETE (least privilege)', !$hasDangerous,
    $hasDangerous ? 'user has extra rights: ' . substr($grants, 0, 120) : 'no DROP/ALTER/CREATE');

$productId = (int) Database::fetchValue('SELECT MIN(id) FROM products');

// CHECK constraint: stock can never be negative
$pdo->beginTransaction();
try {
    Database::execute('UPDATE products SET stock_quantity = -1 WHERE id = :id', ['id' => $productId]);
    $blocked = false;
} catch (PDOException $e) {
    $blocked = true;
}
$pdo->rollBack();
check('DB-05', 'Database refuses negative stock (CHECK constraint)', $blocked);

// CHECK constraint: order total must equal subtotal + delivery - discount
$pdo->beginTransaction();
try {
    $uid = (int) Database::fetchValue("SELECT MIN(id) FROM users");
    Database::execute(
        "INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, recipient_name, recipient_phone,
                             address_line1, city, district, subtotal, delivery_fee, discount_amount, total_amount)
         VALUES ('GA000000-TEST01', :u, 'Test', 't@example.com', '0771234567', 'Test', '0771234567', 'x', 'x', 'Colombo', 100, 10, 0, 999)",
        ['u' => $uid]
    );
    $blocked = false;
} catch (PDOException $e) {
    $blocked = true;
}
$pdo->rollBack();
check('DB-06', 'Database refuses an order whose total does not add up', $blocked);

// Foreign key: a product cannot point to a missing category
$pdo->beginTransaction();
try {
    Database::execute("INSERT INTO products (category_id, name, slug, price) VALUES (999999, 'x', 'system-check-x', 1)");
    $blocked = false;
} catch (PDOException $e) {
    $blocked = true;
}
$pdo->rollBack();
check('DB-07', 'Database refuses a product in a non-existent category (foreign key)', $blocked);

// Row lock: a second connection must wait while the first holds FOR UPDATE
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', config('db.host'), (int) config('db.port'), config('db.name'));
    $second = new PDO($dsn, (string) config('db.user'), (string) config('db.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $second->exec('SET SESSION innodb_lock_wait_timeout = 2');
    $pdo->beginTransaction();
    Database::fetchOne('SELECT id FROM products WHERE id = :id FOR UPDATE', ['id' => $productId]);
    $start = microtime(true);
    $waited = false;
    $second->beginTransaction();
    try {
        $stmt = $second->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([$productId]);
    } catch (PDOException $e) {
        $waited = str_contains($e->getMessage(), 'Lock wait timeout') || (int) ($e->errorInfo[1] ?? 0) === 1205;
    }
    $second->rollBack();
    $pdo->rollBack();
    $seconds = round(microtime(true) - $start, 1);
    check('DB-08', 'Row lock: a second checkout waits while the first holds the product (no overselling)', $waited, "second connection blocked for {$seconds}s");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    check('DB-08', 'Row lock test', false, $e->getMessage());
}

// Ledger consistency: sum of movements = current stock for every product
$mismatch = Database::fetchAll(
    'SELECT p.id, p.name, p.stock_quantity, COALESCE(SUM(m.quantity_change), 0) AS ledger
       FROM products p LEFT JOIN inventory_movements m ON m.product_id = p.id
      GROUP BY p.id, p.name, p.stock_quantity
     HAVING p.stock_quantity <> COALESCE(SUM(m.quantity_change), 0)'
);
check('DB-09', 'Stock ledger matches stock for every product', $mismatch === [],
    $mismatch === [] ? 'all products consistent' : count($mismatch) . ' product(s) differ, e.g. ' . $mismatch[0]['name']);

// Totals consistency for every stored order
$bad = (int) Database::fetchValue(
    'SELECT COUNT(*) FROM orders o
      WHERE o.subtotal <> (SELECT COALESCE(SUM(oi.line_total), 0) FROM order_items oi WHERE oi.order_id = o.id)'
);
check('DB-10', 'Every order subtotal equals the sum of its items', $bad === 0, $bad . ' mismatched');

$noPayment = (int) Database::fetchValue('SELECT COUNT(*) FROM orders o LEFT JOIN payments p ON p.order_id = o.id WHERE p.id IS NULL');
check('DB-11', 'Every order has a payment record', $noPayment === 0, $noPayment . ' without payment');

$plain = (int) Database::fetchValue(
    'SELECT COUNT(*) FROM users WHERE password_hash NOT LIKE :bcrypt AND password_hash NOT LIKE :argon',
    ['bcrypt' => '$2y$%', 'argon' => '$argon2%']
);
check('DB-12', 'No plain-text passwords stored', $plain === 0, $plain . ' suspicious');

$missing = [];
foreach (['products' => ['compare_at_price', 'is_customizable', 'custom_label', 'custom_max_length', 'custom_required'],
          'cart_items' => ['customization_text'], 'order_items' => ['customization_text']] as $table => $columns) {
    $have = array_column(Database::fetchAll('SHOW COLUMNS FROM ' . $table), 'Field');
    foreach ($columns as $column) {
        if (!in_array($column, $have, true)) {
            $missing[] = $table . '.' . $column;
        }
    }
}
check('DB-13', 'Sale-price and personalisation columns exist', $missing === [],
    $missing === [] ? 'all present' : 'missing ' . implode(', ', $missing) . ' - run sql/updates/2026-10-18_customization.sql');

out('');
// ---------------------------------------------------------------------
out('2. Web server and security (Apache + .htaccess)');
// ---------------------------------------------------------------------
[$s, $h, $body, $ms] = http_get($base . '/');
check('WEB-01', 'Homepage loads', $s === 200, "HTTP {$s}, {$ms} ms");
if ($s === 0) {
    out('Cannot reach ' . $base . '/ — is Apache started in XAMPP?');
}
check('WEB-02', 'Content-Security-Policy header present', isset($h['content-security-policy']));
check('WEB-03', 'X-Frame-Options: DENY (no click-jacking)', strtoupper($h['x-frame-options'] ?? '') === 'DENY');
check('WEB-04', 'X-Content-Type-Options: nosniff', strtolower($h['x-content-type-options'] ?? '') === 'nosniff');
check('WEB-05', 'PHP version not advertised (no X-Powered-By)', !isset($h['x-powered-by']), $h['x-powered-by'] ?? 'hidden');
$cookie = $h['set-cookie'] ?? '';
check('WEB-06', 'Session cookie is HttpOnly and SameSite=Lax', stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite=lax') !== false, $cookie !== '' ? 'ok' : 'no cookie seen');

$blockedPaths = [
    '/config/config.php' => 'database password file',
    '/config/config.example.php' => 'config template',
    '/app/bootstrap.php' => 'application code',
    '/sql/schema.sql' => 'database script',
    '/storage/logs/' => 'error logs',
    '/scripts/setup.php' => 'setup script',
    '/tests/README.md' => 'test files',
    '/docs/01-requirements.md' => 'documentation',
    '/.git/config' => 'Git repository',
    '/.htaccess' => 'server rules',
    '/env-check.php' => 'old environment page',
    '/public/env-check.php' => 'old environment page (direct path)',
    '/uploads/products/' => 'upload folder listing',
];
$i = 7;
foreach ($blockedPaths as $path => $label) {
    [$s, , $b] = http_get($base . $path);
    $ok = in_array($s, [403, 404], true) && !str_contains($b, "'password'") && !str_contains($b, 'CREATE TABLE');
    check(sprintf('WEB-%02d', $i++), 'Blocked: ' . $label . ' (' . $path . ')', $ok, "HTTP {$s}");
}

// PHP must not run inside the uploads folder
$probeName = 'system-check-' . bin2hex(random_bytes(4)) . '.php';
$probe = APP_ROOT . '/public/uploads/products/' . $probeName;
$written = @file_put_contents($probe, '<?php echo "RAN-" . "PHP";');
if ($written !== false) {
    [$s, , $b] = http_get($base . '/uploads/products/' . $probeName);
    @unlink($probe);
    check(sprintf('WEB-%02d', $i++), 'A .php file placed in uploads cannot run', !str_contains($b, 'RAN-PHP') && $s !== 200, "HTTP {$s}; test file deleted");
} else {
    check(sprintf('WEB-%02d', $i++), 'Uploads folder is writable for the PHP-execution test', false, 'could not write test file');
}

[$s, $h] = http_get($base . '/admin/');
check(sprintf('WEB-%02d', $i++), 'Guest opening admin is sent to login', in_array($s, [302, 303], true) && str_contains($h['location'] ?? '', 'login.php'), "HTTP {$s}");
[$s, , $b] = http_get($base . '/products.php?q=' . rawurlencode("' OR 1=1 --"));
check(sprintf('WEB-%02d', $i++), 'SQL text in search is harmless', $s === 200 && str_contains($b, '0 products'), "HTTP {$s}");
[$s, , $b] = http_get($base . '/products.php?q=' . rawurlencode('<script>alert(1)</script>'));
check(sprintf('WEB-%02d', $i++), 'Script text in search is shown as text (XSS blocked)', $s === 200 && !str_contains($b, '<script>alert(1)</script>'), "HTTP {$s}");
[$s, , $b] = http_get($base . '/this-page-does-not-exist.php');
check(sprintf('WEB-%02d', $i++), 'Unknown page shows the GiftAura 404 page', $s === 404 && str_contains($b, 'Page not found'), "HTTP {$s}");
[$s] = http_get($base . '/logout.php');
check(sprintf('WEB-%02d', $i++), 'Logout cannot be triggered by a simple link (GET)', in_array($s, [302, 303], true), "HTTP {$s}");

out('');
// ---------------------------------------------------------------------
// ---------------------------------------------------------------------
out('2b. E-mail');
// ---------------------------------------------------------------------
$transport = Mailer::transport();
check('MAIL-01', 'E-mail mode is set', in_array((string) config('mail.transport'), ['smtp', 'file', 'off'], true),
    'mode: ' . $transport . ($transport === 'file' ? ' (test mode - saved to storage/mail)' : ''));
$siteUrl = (string) config('app.site_url', '');
check('MAIL-02', 'Site address for e-mail links (app.site_url)', (bool) preg_match('#^https?://[^/\s]+$#', $siteUrl),
    $siteUrl !== '' ? 'links start with ' . $siteUrl . config('app.base_url') : 'missing - add app.site_url to config/config.php');
$mailDir = APP_ROOT . '/storage/mail';
check('MAIL-03', 'storage/mail is writable (test mode)', $transport !== 'file' || (is_dir($mailDir) && is_writable($mailDir)),
    $transport === 'file' ? $mailDir : 'not needed in this mode');
[$s] = http_get($base . '/storage/mail/.gitkeep');
check('MAIL-04', 'Blocked: saved e-mails (/storage/mail/)', in_array($s, [403, 404], true), "HTTP {$s}");
if ($transport === 'smtp') {
    $cafile = (string) config('mail.cafile', '');
    $complete = config('mail.username') && config('mail.password') && ($cafile === '' || is_file($cafile));
    check('MAIL-05', 'SMTP settings complete', (bool) $complete, config('mail.host') . ':' . config('mail.port') . ' as ' . config('mail.username')
        . ($cafile !== '' && !is_file($cafile) ? ' - certificate file not found: ' . $cafile : '') . ' (send a test: scripts\\test_mail.php)');
} else {
    check('MAIL-05', 'SMTP settings complete', true, 'not needed in this mode');
}
$failedMail = EmailService::failedCount();
check('MAIL-06', 'No failed e-mails in the last 7 days', $failedMail === 0, $failedMail . ' failed - see Admin > E-mail log');

out('');
out('3. Performance (NFR-18: pages under 1 second on the local server)');
// ---------------------------------------------------------------------
$slug = (string) Database::fetchValue('SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1');
$pages = [
    'Homepage' => '/',
    'Shop (all products)' => '/products.php',
    'Search' => '/products.php?q=rose',
    'Category' => '/products.php?category=' . (string) Database::fetchValue('SELECT slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order LIMIT 1'),
    'Product page' => '/product.php?slug=' . rawurlencode($slug),
    'Cart' => '/cart.php',
];
$p = 1;
foreach ($pages as $label => $path) {
    http_get($base . $path); // warm-up
    $times = [];
    for ($n = 0; $n < 3; $n++) {
        [$s, , , $ms] = http_get($base . $path);
        $times[] = $ms;
    }
    sort($times);
    $median = $times[1];
    check(sprintf('PERF-%02d', $p++), $label, $s === 200 && $median < 1000, "median {$median} ms of 3");
}

out('');
$passed = count(array_filter($results));
$total = count($results);
out(sprintf('RESULT: %d of %d checks passed%s', $passed, $total, $passed === $total ? ' — all good.' : ' — see FAIL lines above.'));

$file = APP_ROOT . '/docs/system-check-' . date('Y-m-d-Hi') . '.txt';
@file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
out('Saved to docs/' . basename($file));
exit($passed === $total ? 0 : 1);
