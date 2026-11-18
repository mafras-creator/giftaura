<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

// ---------------------------------------------------------------------
// 1. Configuration
// ---------------------------------------------------------------------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "GiftAura is not configured yet.\n\n"
       . "Open PowerShell in the giftaura folder and run:\n"
       . "    C:\\xampp\\php\\php.exe scripts\\setup.php\n";
    exit;
}
$GLOBALS['GA_CONFIG'] = require $configFile;
require APP_DIR . '/helpers.php';

date_default_timezone_set(config('app.timezone', 'Asia/Colombo'));
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------
// 2. Class autoloader (no Composer). Looks in app/core and app/services.
// ---------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    foreach (['core', 'services'] as $folder) {
        $file = APP_DIR . '/' . $folder . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

// ---------------------------------------------------------------------
// 3. Error handling: log everything, show customers a generic page.
// ---------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    Logger::error('Uncaught ' . get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'url'  => $_SERVER['REQUEST_URI'] ?? 'cli',
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debugMessage = config('app.debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
    try {
        View::render('errors/500', ['title' => 'Something went wrong', 'debugMessage' => $debugMessage, 'layout' => 'error']);
    } catch (Throwable) {
        echo 'Sorry, something went wrong. Please try again later.';
    }
});

// ---------------------------------------------------------------------
// 4. Security headers (web requests only)
// ---------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
         . "script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By'); // do not advertise the PHP version
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000'); // only meaningful on a real HTTPS server
    }
}

// ---------------------------------------------------------------------
// 5. Session
// ---------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    Session::start();
    // Pages for logged-in users contain private data (orders, addresses, admin screens):
    // tell the browser not to keep them, so "Back" after logging out cannot show them.
    if (isset($_SESSION['user_id']) && !headers_sent()) {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
    }
}
