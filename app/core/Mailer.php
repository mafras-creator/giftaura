<?php
/**
 * GiftAura - sends e-mail. No external library is used.
 *
 * Transports (config 'mail' => 'transport'):
 *   'file' - TEST MODE (default). Each e-mail is saved as an .html file in
 *            storage/mail/ instead of being sent. Open it from disk to see
 *            exactly what the customer would receive.
 *   'smtp' - Sends through an SMTP server, e.g. Gmail:
 *            host smtp.gmail.com, port 465, encryption 'ssl',
 *            username = your Gmail address, password = a Gmail App Password.
 *   'off'  - E-mails are not sent (only recorded in the e-mail log).
 *
 * Security:
 *   - TLS certificates are always verified (never switched off).
 *   - Addresses and subjects containing line breaks are refused
 *     (prevents e-mail header injection).
 *   - The SMTP password is never written to logs or error messages.
 */
declare(strict_types=1);

final class Mailer
{
    private const TIMEOUT = 20;

    /** Last SMTP conversation (commands only, no password), for scripts/test_mail.php. */
    public static array $trace = [];

    public static function transport(): string
    {
        $t = (string) config('mail.transport', 'file');
        return in_array($t, ['smtp', 'file', 'off'], true) ? $t : 'file';
    }

    /**
     * Send one e-mail. Returns ['status' => 'sent'|'saved'|'skipped'|'failed', 'error' => ?string].
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $toEmail . $toName . $subject)) {
            return ['status' => 'failed', 'error' => 'Invalid recipient or subject.'];
        }

        switch (self::transport()) {
            case 'off':
                return ['status' => 'skipped', 'error' => null];
            case 'file':
                return self::saveToFile($toEmail, $toName, $subject, $html);
            default:
                try {
                    self::sendSmtp($toEmail, $toName, $subject, $html, $text);
                    return ['status' => 'sent', 'error' => null];
                } catch (Throwable $e) {
                    return ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)];
                }
        }
    }

    // -----------------------------------------------------------------
    // Test mode: save to storage/mail/
    // -----------------------------------------------------------------

    private static function saveToFile(string $toEmail, string $toName, string $subject, string $html): array
    {
        $dir = APP_ROOT . '/storage/mail';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['status' => 'failed', 'error' => 'Cannot create storage/mail folder.'];
        }
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($toEmail . '-' . $subject)) ?? 'mail';
        $file = $dir . '/' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '-' . substr(trim($slug, '-'), 0, 60) . '.html';
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $page = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $e($subject) . '</title></head><body style="margin:0;background:#eee;">'
              . '<div style="font:14px/1.5 Segoe UI,Arial,sans-serif;background:#fff8d6;border-bottom:1px solid #e0c96a;padding:12px 16px;">'
              . '<strong>TEST MODE - this e-mail was saved, not sent.</strong><br>'
              . 'To: ' . $e($toName) . ' &lt;' . $e($toEmail) . '&gt;<br>'
              . 'From: ' . $e(self::fromName()) . ' &lt;' . $e(self::fromEmail()) . '&gt;<br>'
              . 'Subject: ' . $e($subject) . '<br>Date: ' . $e(date('Y-m-d H:i:s')) . '</div>'
              . $html . '</body></html>';
        if (@file_put_contents($file, $page) === false) {
            return ['status' => 'failed', 'error' => 'Cannot write to storage/mail.'];
        }
        return ['status' => 'saved', 'error' => null];
    }

    // -----------------------------------------------------------------
    // SMTP
    // -----------------------------------------------------------------

    public static function fromEmail(): string
    {
        foreach ([config('mail.from_email'), config('mail.username'), setting('store_email')] as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }
        return 'no-reply@localhost';
    }

    private static function fromName(): string
    {
        return (string) config('mail.from_name', setting('store_name', 'GiftAura'));
    }

    private static function sendSmtp(string $toEmail, string $toName, string $subject, string $html, string $text): void
    {
        self::$trace = [];
        $host = (string) config('mail.host', 'smtp.gmail.com');
        $port = (int) config('mail.port', 465);
        $encryption = (string) config('mail.encryption', 'ssl'); // ssl | tls | none
        $user = (string) config('mail.username', '');
        $pass = (string) config('mail.password', '');
        $from = self::fromEmail();

        if (!in_array($encryption, ['ssl', 'tls'], true) && !in_array($host, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('Unencrypted SMTP is only allowed to this computer. Use encryption ssl (port 465) or tls (port 587).');
        }
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false, 'peer_name' => $host];
        $cafile = (string) config('mail.cafile', '');
        if ($cafile !== '' && is_file($cafile)) {
            $ssl['cafile'] = $cafile;
        }
        $context = stream_context_create(['ssl' => $ssl]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException("Cannot connect to {$host}:{$port} ({$errstr}). Check internet access, host, port and certificates.");
        }
        stream_set_timeout($socket, self::TIMEOUT);

        try {
            self::expect($socket, 220);
            $hostname = 'localhost';
            self::command($socket, 'EHLO ' . $hostname, 250);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('TLS could not be started (certificate problem?).');
                }
                self::command($socket, 'EHLO ' . $hostname, 250);
            }
            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($user), 334, '[username]');
                self::command($socket, base64_encode($pass), 235, '[password hidden]',
                    'Login to the mail server was refused. For Gmail use an App Password (not your normal password).');
            }
            self::command($socket, 'MAIL FROM:<' . $from . '>', 250);
            self::command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            self::command($socket, 'DATA', 354);

            $message = self::buildMessage($from, $toEmail, $toName, $subject, $html, $text);
            // Dot-stuffing: a line starting with "." must be sent as ".."
            $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
            $message = str_replace("\n", "\r\n", $message);
            fwrite($socket, $message . "\r\n.\r\n");
            self::$trace[] = 'C: [message body]';
            self::expect($socket, 250);
            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    /** @param int|int[] $expected */
    private static function command($socket, string $line, int|array $expected, ?string $shown = null, ?string $friendlyError = null): string
    {
        fwrite($socket, $line . "\r\n");
        self::$trace[] = 'C: ' . ($shown ?? $line);
        return self::expect($socket, $expected, $friendlyError);
    }

    /** Read a (possibly multi-line) reply and check its code. */
    private static function expect($socket, int|array $expected, ?string $friendlyError = null): string
    {
        $reply = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $reply = trim($reply);
        self::$trace[] = 'S: ' . $reply;
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            $meta = stream_get_meta_data($socket);
            if ($reply === '' && !empty($meta['timed_out'])) {
                throw new RuntimeException('The mail server did not answer in time.');
            }
            throw new RuntimeException(($friendlyError !== null ? $friendlyError . ' ' : '') . 'Server said: ' . mb_substr($reply, 0, 150));
        }
        return $reply;
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /** "Name" for plain names, =?UTF-8?B?...?= (not quoted) for names with other characters. */
    private static function displayName(string $name): string
    {
        if ($name === '') {
            return '';
        }
        return preg_match('/[^\x20-\x7E]/', $name) ? self::encodeHeader($name) . ' ' : '"' . $name . '" ';
    }

    private static function buildMessage(string $from, string $toEmail, string $toName, string $subject, string $html, string $text): string
    {
        $boundary = 'ga-' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $fromName = str_replace(['"', "\r", "\n"], '', self::fromName());
        $toName = str_replace(['"', "\r", "\n"], '', $toName);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::displayName($fromName) . '<' . $from . '>',
            'To: ' . self::displayName($toName) . '<' . $toEmail . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: GiftAura',
        ];
        $body = "--{$boundary}\n"
              . "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n"
              . chunk_split(base64_encode($text), 76, "\n")
              . "--{$boundary}\n"
              . "Content-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n"
              . chunk_split(base64_encode($html), 76, "\n")
              . "--{$boundary}--";
        return implode("\n", $headers) . "\n\n" . $body;
    }
}
