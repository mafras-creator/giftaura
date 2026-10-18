<?php
/**
 * GiftAura - send one test e-mail with the current settings (command line only).
 *
 * Usage, from the giftaura folder:
 *     C:\xampp\php\php.exe scripts\test_mail.php
 *
 * Shows the result and, for Gmail, the conversation with the mail server
 * (the password is never shown). The attempt is also written to the
 * admin e-mail log.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

fwrite(STDOUT, PHP_EOL . '=== GiftAura test e-mail ===' . PHP_EOL);
fwrite(STDOUT, 'Mode: ' . Mailer::transport() . (Mailer::transport() === 'smtp'
    ? ' via ' . config('mail.host') . ':' . config('mail.port') . ' as ' . config('mail.username') : '') . PHP_EOL);
fwrite(STDOUT, 'Send the test e-mail to: ');
$to = trim((string) fgets(STDIN));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDOUT, "That is not a valid e-mail address.\n");
    exit(1);
}

$result = EmailService::sendTest($to);
foreach (Mailer::$trace as $line) {
    fwrite(STDOUT, '    ' . $line . PHP_EOL);
}
fwrite(STDOUT, PHP_EOL);
switch ($result['status']) {
    case 'sent':
        fwrite(STDOUT, "[OK] Sent. Check the inbox of {$to} (and the Spam folder) in a minute.\n");
        break;
    case 'saved':
        fwrite(STDOUT, "[OK] Test mode: the e-mail was saved in storage\\mail\\ (open the newest file).\n");
        break;
    case 'skipped':
        fwrite(STDOUT, "[--] E-mail is switched off (mode 'off'). Nothing was sent.\n");
        break;
    default:
        fwrite(STDOUT, '[FAIL] ' . $result['error'] . PHP_EOL);
        fwrite(STDOUT, "See docs/troubleshooting.md, section 'E-mail'.\n");
        exit(1);
}
fwrite(STDOUT, PHP_EOL);
