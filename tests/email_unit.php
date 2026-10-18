<?php
/**
 * GiftAura - small unit checks for the e-mail code (test only).
 * Run from the tests folder: php email_unit.php
 * Uses an in-memory SQLite database; nothing is sent.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(__DIR__ . '/schema_sqlite.sql'));
(new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
$GLOBALS['GA_CONFIG']['mail']['transport'] = 'off';

$pass = 0; $fail = 0;
function check(string $name, mixed $got, mixed $want): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  ok   $name\n"; } else { $fail++; echo "  FAIL $name: expected [" . var_export($want, true) . "] got [" . var_export($got, true) . "]\n"; }
}

echo "== Mailer input checks\n";
check('CR/LF in subject refused', Mailer::send('a@example.com', 'A', "Hi\r\nBcc: x@evil.example", '<p>x</p>', 'x')['status'], 'failed');
check('CR/LF in name refused', Mailer::send('a@example.com', "A\nBcc: x@evil.example", 'Hi', '<p>x</p>', 'x')['status'], 'failed');
check('invalid address refused', Mailer::send('not-an-email', 'A', 'Hi', '<p>x</p>', 'x')['status'], 'failed');
check('two addresses refused', Mailer::send('a@example.com,b@example.com', 'A', 'Hi', '<p>x</p>', 'x')['status'], 'failed');
check('mode off -> skipped', Mailer::send('a@example.com', 'A', 'Hi', '<p>x</p>', 'x')['status'], 'skipped');
$GLOBALS['GA_CONFIG']['mail']['transport'] = 'nonsense';
check('unknown mode falls back to test mode', Mailer::transport(), 'file');

echo "== Tokens\n";
$pdo->exec("INSERT INTO users (role, full_name, email, password_hash) VALUES ('customer','T','t@example.com','x')");
$s1 = TokenService::create(1, TokenService::RESET);
check('secret format', TokenService::looksValid($s1), true);
check('peek finds user', TokenService::peek($s1, TokenService::RESET), 1);
check('wrong purpose refused', TokenService::peek($s1, TokenService::VERIFY), null);
check('upper-case / altered secret refused', TokenService::peek(strtoupper($s1), TokenService::RESET), null);
check('consume once', TokenService::consume($s1, TokenService::RESET), 1);
check('consume twice refused', TokenService::consume($s1, TokenService::RESET), null);
$s2 = TokenService::create(1, TokenService::RESET);
$s3 = TokenService::create(1, TokenService::RESET);
check('new link cancels older one', TokenService::peek($s2, TokenService::RESET), null);
check('newest link valid', TokenService::peek($s3, TokenService::RESET), 1);
check('limit 3 per hour reached', TokenService::tooMany(1, TokenService::RESET), true);
$pdo->exec('UPDATE users SET is_active = 0');
check('inactive account link refused', TokenService::peek($s3, TokenService::RESET), null);
try { TokenService::create(1, 'other'); check('unknown purpose throws', false, true); }
catch (InvalidArgumentException $e) { check('unknown purpose throws', true, true); }

echo "== Result: $pass passed, $fail failed\n";
