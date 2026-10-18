<?php
/**
 * Tiny fake SMTP server for tests (no encryption). NEVER use in production.
 *   php fake_smtp.php 2525 ok      accepts user "shop@example.com" / password "goodpassword"
 *   php fake_smtp.php 2525 reject  answers every login with 535 (wrong password)
 * Every command and message is appended to tests/smtp_inbox.txt.
 */
[$port, $mode] = [(int) ($argv[1] ?? 2525), $argv[2] ?? 'ok'];
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if ($server === false) { fwrite(STDERR, "$errstr\n"); exit(1); }
$log = __DIR__ . '/smtp_inbox.txt';
while ($c = @stream_socket_accept($server, -1)) {
    $say = static function (string $line) use ($c): void { fwrite($c, $line . "\r\n"); };
    $say('220 fake.smtp ready');
    $step = '';
    $user = '';
    while (($line = fgets($c)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($step === 'user') { $user = base64_decode($line); $step = 'pass'; $say('334 UGFzc3dvcmQ6'); continue; }
        if ($step === 'pass') {
            $ok = $mode === 'ok' && $user === 'shop@example.com' && base64_decode($line) === 'goodpassword';
            file_put_contents($log, "AUTH $user " . ($ok ? 'accepted' : 'rejected') . "\n", FILE_APPEND);
            $step = '';
            $say($ok ? '235 2.7.0 Accepted' : '535 5.7.8 Username and Password not accepted');
            continue;
        }
        if ($step === 'data') {
            if ($line === '.') { $step = ''; file_put_contents($log, "END DATA\n", FILE_APPEND); $say('250 2.0.0 OK queued'); continue; }
            file_put_contents($log, $line . "\n", FILE_APPEND);
            continue;
        }
        file_put_contents($log, 'C: ' . $line . "\n", FILE_APPEND);
        $cmd = strtoupper(substr($line, 0, 4));
        match (true) {
            $cmd === 'EHLO' => fwrite($c, "250-fake.smtp\r\n250 AUTH LOGIN\r\n"),
            $line === 'AUTH LOGIN' => [$step = 'user', $say('334 VXNlcm5hbWU6')],
            $cmd === 'MAIL', $cmd === 'RCPT' => $say('250 2.1.0 OK'),
            $cmd === 'DATA' => [$step = 'data', $say('354 Go ahead')],
            $cmd === 'QUIT' => $say('221 2.0.0 Bye'),
            default => $say('502 5.5.1 Unrecognized command'),
        };
        if ($cmd === 'QUIT') { break; }
    }
    fclose($c);
}
