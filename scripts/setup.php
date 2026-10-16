<?php
/**
 * GiftAura - first-time setup (command line only).
 *
 * Usage, from the giftaura folder in PowerShell:
 *     C:\xampp\php\php.exe scripts\setup.php
 *
 * What it does:
 *   1. Checks the PHP version and required extensions.
 *   2. Asks for the MariaDB port and the giftaura_app password.
 *   3. Tests the database connection and the imported data.
 *   4. Writes config/config.php (which is never committed to Git).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

$root       = dirname(__DIR__);
$configFile = $root . '/config/config.php';

function say(string $text = ''): void { fwrite(STDOUT, $text . PHP_EOL); }
function ok(string $text): void { say('  [OK]   ' . $text); }
function bad(string $text): void { say('  [FAIL] ' . $text); }
function warn(string $text): void { say('  [WARN] ' . $text); }
function ask(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $question . $suffix . ': ');
    $answer = fgets(STDIN);
    $answer = $answer === false ? '' : trim($answer);
    return $answer === '' ? $default : $answer;
}

say('');
say('=== GiftAura setup ===');
say('');

// ---------------------------------------------------------------------
// 1. PHP checks
// ---------------------------------------------------------------------
say('1. Checking PHP');
$fatal = false;
if (PHP_VERSION_ID >= 80100) {
    ok('PHP ' . PHP_VERSION);
} else {
    bad('PHP ' . PHP_VERSION . ' - GiftAura needs PHP 8.1 or newer');
    $fatal = true;
}
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'openssl'] as $ext) {
    if (extension_loaded($ext)) {
        ok("extension {$ext}");
    } else {
        bad("extension {$ext} is missing - enable extension={$ext} in C:\\xampp\\php\\php.ini and restart Apache");
        $fatal = true;
    }
}
$tz = (string) ini_get('date.timezone');
if ($tz === 'Asia/Colombo') {
    ok('date.timezone = Asia/Colombo');
} else {
    warn("date.timezone is '{$tz}'. GiftAura sets Asia/Colombo itself, but you can also set it in php.ini.");
}
if ($fatal) {
    say('');
    say('Please fix the [FAIL] items above, then run this script again.');
    exit(1);
}

// ---------------------------------------------------------------------
// 2. Existing configuration?
// ---------------------------------------------------------------------
say('');
if (is_file($configFile)) {
    $answer = strtolower(ask('config/config.php already exists. Replace it? (y/n)', 'n'));
    if ($answer !== 'y') {
        say('Nothing changed. Your existing configuration was kept.');
        exit(0);
    }
}

// ---------------------------------------------------------------------
// 3. Ask for database details
// ---------------------------------------------------------------------
say('2. Database details (press Enter to accept the value in [brackets])');
$host = ask('   Database host', '127.0.0.1');
$port = (int) ask('   MariaDB port (see XAMPP Control Panel, MySQL row)', '3307');
$name = ask('   Database name', 'giftaura_db');
$user = ask('   Database user', 'giftaura_app');
say('   Note: on Windows the password is visible while you type. That is normal.');
$pass = ask('   Password for ' . $user);
if ($pass === '') {
    bad('A password is required (the one you chose when running 00_create_database_and_user.sql).');
    exit(1);
}
$baseUrl = '/' . trim(ask('   Website folder under http://localhost', '/giftaura'), '/');

// ---------------------------------------------------------------------
// 4. Test the connection
// ---------------------------------------------------------------------
say('');
say('3. Testing the database connection');
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ok("Connected to {$name} on {$host}:{$port} as {$user}");
} catch (PDOException $e) {
    bad('Could not connect: ' . $e->getMessage());
    say('');
    say('Common causes:');
    say('  - MySQL is not started in the XAMPP Control Panel');
    say('  - Wrong port (check the MySQL row in XAMPP, e.g. 3306 or 3307)');
    say('  - Wrong password (it is the one typed into phpMyAdmin when creating giftaura_app)');
    say('  - sql/00_create_database_and_user.sql has not been run yet');
    exit(1);
}

try {
    $counts = $pdo->query(
        'SELECT (SELECT COUNT(*) FROM categories) AS categories,
                (SELECT COUNT(*) FROM products)   AS products,
                (SELECT COUNT(*) FROM settings)   AS settings,
                (SELECT COUNT(*) FROM users WHERE role = \'admin\') AS admins'
    )->fetch(PDO::FETCH_ASSOC);
    ok("Tables found: {$counts['categories']} categories, {$counts['products']} products, {$counts['settings']} settings");
    if ((int) $counts['settings'] === 0) {
        warn('The settings table is empty - import sql/seed.sql in phpMyAdmin.');
    }
} catch (PDOException $e) {
    bad('The tables are missing: import sql/schema.sql and sql/seed.sql in phpMyAdmin first.');
    exit(1);
}

// ---------------------------------------------------------------------
// 5. Write config/config.php
// ---------------------------------------------------------------------
$config = [
    'db' => ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'password' => $pass],
    'app' => ['base_url' => $baseUrl, 'timezone' => 'Asia/Colombo', 'debug' => false],
    'security' => [
        'session_name'         => 'GIFTAURA_SID',
        'session_idle_minutes' => 30,
        'login_max_per_email'  => 5,
        'login_max_per_ip'     => 20,
        'login_window_minutes' => 15,
    ],
];
$php = "<?php\n"
     . "/**\n"
     . " * GiftAura configuration - created by scripts/setup.php on " . date('Y-m-d H:i') . ".\n"
     . " * Contains the database password: NEVER commit this file to Git (it is in .gitignore).\n"
     . " * Set 'debug' => true only while developing, to see error details on the error page.\n"
     . " */\n"
     . 'return ' . var_export($config, true) . ";\n";

if (file_put_contents($configFile, $php) === false) {
    bad('Could not write config/config.php - check the folder permissions.');
    exit(1);
}
ok('Saved config/config.php');

$logDir = $root . '/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ok('storage/logs is writable');
} else {
    warn('storage/logs is not writable - errors cannot be logged.');
}

say('');
say('Setup complete.');
if ((int) ($counts['admins'] ?? 0) === 0) {
    say('Next step - create your administrator account:');
    say('    C:\\xampp\\php\\php.exe scripts\\create_admin.php');
} else {
    say('Open the shop: http://localhost' . $baseUrl . '/');
}
say('');
