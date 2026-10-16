<?php
/**
 * GiftAura - create an administrator account (command line only).
 *
 * Usage, from the giftaura folder in PowerShell:
 *     C:\xampp\php\php.exe scripts\create_admin.php
 *
 * There is deliberately no default admin account and no way to become an
 * administrator through the website. Only someone with access to this
 * computer can create an administrator.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

function ask(string $question): string
{
    fwrite(STDOUT, $question . ': ');
    $answer = fgets(STDIN);
    return $answer === false ? '' : rtrim($answer, "\r\n");
}

fwrite(STDOUT, PHP_EOL . '=== Create a GiftAura administrator ===' . PHP_EOL);
fwrite(STDOUT, 'Password rules: at least 8 characters, with a letter and a number.' . PHP_EOL);
fwrite(STDOUT, 'Note: on Windows the password is visible while you type. That is normal.' . PHP_EOL . PHP_EOL);

$input = [
    'full_name'        => trim(ask('Full name')),
    'email'            => trim(ask('Email (used to log in)')),
    'phone'            => trim(ask('Mobile number (optional, press Enter to skip)')),
    'password'         => ask('Password'),
    'password_confirm' => ask('Type the password again'),
];

$result = UserService::createAdmin($input);

if (isset($result['errors'])) {
    fwrite(STDOUT, PHP_EOL . 'The administrator was NOT created:' . PHP_EOL);
    foreach ($result['errors'] as $message) {
        fwrite(STDOUT, '  - ' . $message . PHP_EOL);
    }
    fwrite(STDOUT, 'Please run the script again.' . PHP_EOL);
    exit(1);
}

Database::execute(
    'INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, details, ip_address)
     VALUES (:admin, :action, :type, :entity, :details, :ip)',
    [
        'admin'   => $result['id'],
        'action'  => 'admin.create',
        'type'    => 'user',
        'entity'  => $result['id'],
        'details' => 'Administrator created from the command line (scripts/create_admin.php)',
        'ip'      => 'cli',
    ]
);

fwrite(STDOUT, PHP_EOL . 'Administrator created successfully.' . PHP_EOL);
fwrite(STDOUT, 'Log in at: http://localhost' . url('login.php') . PHP_EOL . PHP_EOL);
