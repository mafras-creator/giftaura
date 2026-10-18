<?php
/**
 * GiftAura - choose how e-mails are sent (command line only).
 *
 * Usage, from the giftaura folder in PowerShell:
 *     C:\xampp\php\php.exe scripts\setup_mail.php
 *
 * Modes:
 *   1. Test mode  - e-mails are saved as files in storage\mail\ (nothing is sent)
 *   2. Gmail      - e-mails are really sent from your Gmail account
 *   3. Off        - no e-mails (only recorded in the admin e-mail log)
 *
 * Gmail needs an APP PASSWORD, not your normal password:
 *   Google Account -> Security -> 2-Step Verification (turn on)
 *   -> App passwords -> create one called "GiftAura" -> copy the 16 letters.
 *
 * Only config/config.php is changed (it is never committed to Git).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDOUT, "config/config.php not found. Run scripts\\setup.php first.\n");
    exit(1);
}
$config = require $configFile;

function ask(string $question, string $default = ''): string
{
    fwrite(STDOUT, $question . ($default !== '' ? " [{$default}]" : '') . ': ');
    $answer = fgets(STDIN);
    $answer = $answer === false ? '' : trim($answer);
    return $answer === '' ? $default : $answer;
}

$mail = ($config['mail'] ?? []) + [
    'transport' => 'file', 'host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl',
    'username' => '', 'password' => '', 'from_email' => '', 'from_name' => 'GiftAura', 'cafile' => '',
];

fwrite(STDOUT, PHP_EOL . '=== GiftAura e-mail set-up ===' . PHP_EOL);
fwrite(STDOUT, 'Current mode: ' . $mail['transport'] . ($mail['transport'] === 'smtp' ? ' (' . $mail['username'] . ')' : '') . PHP_EOL . PHP_EOL);
fwrite(STDOUT, "  1 = Test mode: save e-mails as files in storage\\mail (recommended while developing)\n");
fwrite(STDOUT, "  2 = Gmail: really send e-mails from your Gmail account\n");
fwrite(STDOUT, "  3 = Off: do not send e-mails\n\n");
$choice = ask('Choose 1, 2 or 3', $mail['transport'] === 'smtp' ? '2' : ($mail['transport'] === 'off' ? '3' : '1'));

if ($choice === '2') {
    fwrite(STDOUT, "\nGmail needs an APP PASSWORD (16 letters), not your normal Gmail password.\n");
    fwrite(STDOUT, "Create one: Google Account > Security > 2-Step Verification > App passwords.\n\n");
    $address = strtolower(ask('Your Gmail address', $mail['username']));
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDOUT, "That is not a valid e-mail address. Nothing was changed.\n");
        exit(1);
    }
    fwrite(STDOUT, "Note: on Windows the password is visible while you type. That is normal.\n");
    $password = str_replace(' ', '', ask('App password' . ($mail['password'] !== '' ? ' (press Enter to keep the saved one)' : '')));
    if ($password === '') {
        $password = (string) $mail['password'];
    }
    if (!preg_match('/^[A-Za-z]{16}$/', $password)) {
        fwrite(STDOUT, "A Gmail app password is 16 letters (spaces are removed automatically). Nothing was changed.\n");
        exit(1);
    }
    $mail = array_merge($mail, [
        'transport' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl',
        'username' => $address, 'password' => $password, 'from_email' => $address,
        'from_name' => ask('Sender name shown to customers', (string) ($mail['from_name'] ?: 'GiftAura')),
    ]);
    // Windows PHP often has no certificate list; XAMPP ships one with Apache.
    if ((string) ini_get('openssl.cafile') === '' && $mail['cafile'] === '') {
        foreach (['C:\\xampp\\apache\\bin\\curl-ca-bundle.crt', 'C:\\xampp\\php\\extras\\ssl\\cacert.pem'] as $candidate) {
            if (is_file($candidate)) {
                $mail['cafile'] = $candidate;
                fwrite(STDOUT, "Using certificate list: {$candidate}\n");
                break;
            }
        }
    }
} elseif ($choice === '3') {
    $mail['transport'] = 'off';
} else {
    $mail['transport'] = 'file';
}

$config['mail'] = $mail;
$config['app']['site_url'] = $config['app']['site_url'] ?? 'http://localhost';

$php = "<?php\n"
     . "/**\n"
     . " * GiftAura configuration - updated by scripts/setup_mail.php on " . date('Y-m-d H:i') . ".\n"
     . " * Contains the database and e-mail passwords: NEVER commit this file to Git (it is in .gitignore).\n"
     . " */\n"
     . 'return ' . var_export($config, true) . ";\n";
if (file_put_contents($configFile, $php) === false) {
    fwrite(STDOUT, "Could not write config/config.php.\n");
    exit(1);
}

fwrite(STDOUT, PHP_EOL . 'Saved. E-mail mode is now: ' . $mail['transport'] . PHP_EOL);
if ($mail['transport'] === 'smtp') {
    fwrite(STDOUT, "Next: send a test e-mail to yourself:\n    C:\\xampp\\php\\php.exe scripts\\test_mail.php\n");
} elseif ($mail['transport'] === 'file') {
    fwrite(STDOUT, "E-mails will appear as files in storage\\mail\\ (double-click to open).\n");
}
fwrite(STDOUT, PHP_EOL);
