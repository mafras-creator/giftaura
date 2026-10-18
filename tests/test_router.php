<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_starts_with($uri, '/giftaura/')) { http_response_code(404); return true; }
$rel = substr($uri, strlen('/giftaura/'));
$file = dirname(__DIR__) . '/public/' . $rel;
if (is_dir($file)) { $file = rtrim($file, '/') . '/index.php'; }
if (!is_file($file)) { $file = __DIR__ . '/giftaura/public/404.php'; }
if (!str_ends_with($file, '.php')) {
    // Serve images, CSS and JS from public/ (like Apache does).
    $types = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) { http_response_code(403); return true; }
    header('Content-Type: ' . $types[$ext]);
    readfile($file);
    return true;
}
require dirname(__DIR__) . '/app/core/Database.php';
// Test-only PDO: SQLite does not support "FOR UPDATE" row locks (it locks the whole DB in a write transaction).
class TestPdo extends PDO {
    public function prepare(string $q, array $o = []): PDOStatement|false { return parent::prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $q), $o); }
}
$pdo = new TestPdo('sqlite:' . __DIR__ . '/test.db', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->exec('PRAGMA busy_timeout = 5000');
(new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
$_SERVER['SCRIPT_FILENAME'] = $file;
$_SERVER['SCRIPT_NAME'] = '/giftaura/public/' . $rel;
chdir(dirname($file));
require $file;
