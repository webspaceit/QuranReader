<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Plain-PHP mailer with three transports:
 *   mail  - PHP mail() (works on most shared hosting accounts)
 *   smtp  - authenticated SMTP over TLS (recommended when mail() is blocked)
 *   log   - writes to storage/logs/mail.log (local development only)
 *
 * No external library is required, so the project runs on hosts without Composer.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $fromEmail = (string) Config::get('mail.from_email', 'no-reply@localhost');
        $fromName  = (string) Config::get('mail.from_name', Config::get('app.name', 'Quran Reader'));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('Mailer: invalid recipient', ['to' => $to]);
            return false;
        }

        $textBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));
        $boundary = 'b' . bin2hex(random_bytes(12));

        $headers = [
            'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: QuranReader',
        ];

        $body  = "This is a multi-part message in MIME format.\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode(self::wrapHtml($htmlBody, $subject)) . "\r\n\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $transport = (string) Config::get('mail.transport', 'mail');

        try {
            if ($transport === 'log') {
                return self::log($to, $subject, $htmlBody, $textBody);
            }
            if ($transport === 'smtp') {
                return self::sendSmtp($to, $fromEmail, $subject, implode("\r\n", $headers), $body);
            }
            return self::sendMail($to, $subject, $body, $headers, $fromEmail);
        } catch (\Throwable $e) {
            Logger::error('Mail sending failed: ' . $e->getMessage(), ['to' => $to, 'transport' => $transport]);
            return false;
        }
    }

    public static function isConfigured(): bool
    {
        $transport = (string) Config::get('mail.transport', 'mail');
        if ($transport === 'smtp') {
            return (string) Config::get('mail.smtp.host', '') !== '';
        }
        return true;
    }

    /** @param array<int,string> $headers */
    private static function sendMail(string $to, string $subject, string $body, array $headers, string $fromEmail): bool
    {
        $subject = self::encodeName($subject);
        $params  = '-f' . $fromEmail;
        $result  = @mail($to, $subject, $body, implode("\r\n", $headers), $params);

        if (!$result) {
            Logger::warning('Mailer: mail() returned false', ['to' => $to]);
        }

        return (bool) $result;
    }

    private static function sendSmtp(string $to, string $fromEmail, string $subject, string $headers, string $body): bool
    {
        $host       = (string) Config::get('mail.smtp.host', '');
        $port       = (int) Config::get('mail.smtp.port', 587);
        $username   = (string) Config::get('mail.smtp.username', '');
        $password   = (string) Config::get('mail.smtp.password', '');
        $encryption = strtolower((string) Config::get('mail.smtp.encryption', 'tls'));

        if ($host === '') {
            throw new \RuntimeException('SMTP host is not configured.');
        }

        $remote  = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($socket, 20);

        try {
            self::expect($socket, [220]);
            $hostname = parse_url((string) Config::get('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';
            self::command($socket, 'EHLO ' . $hostname, [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Unable to start TLS on the SMTP connection.');
                }
                self::command($socket, 'EHLO ' . $hostname, [250]);
            }

            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode($password), [235]);
            }

            self::command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $data = 'To: <' . $to . ">\r\n"
                . 'Subject: ' . self::encodeName($subject) . "\r\n"
                . 'Date: ' . date('r') . "\r\n"
                . $headers . "\r\n\r\n"
                . $body;

            // Dot-stuffing per RFC 5321.
            $data = preg_replace('/^\./m', '..', $data);
            fwrite($socket, $data . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return true;
    }

    /**
     * @param resource $socket
     * @param array<int,int> $expected
     */
    private static function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expected);
    }

    /**
     * @param resource $socket
     * @param array<int,int> $expected
     */
    private static function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private static function log(string $to, string $subject, string $html, string $text): bool
    {
        $dir = QURAN_STORAGE . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n",
            date('c'),
            $to,
            $subject,
            $text !== '' ? $text : strip_tags($html)
        );
        return @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function encodeName(string $value): string
    {
        if (preg_match('/[\x80-\xFF]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private static function wrapHtml(string $inner, string $subject): string
    {
        $brand = e((string) Config::get('app.name', 'Quran Reader'));
        $title = e($subject);
        return '<!doctype html><html><body style="margin:0;padding:24px;background:#FAF8F2;font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1F2937">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;border:1px solid #E7E1D5">'
            . '<div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#0F5132;margin-bottom:10px">' . $brand . '</div>'
            . '<h1 style="font-size:19px;margin:0 0 16px">' . $title . '</h1>'
            . '<div style="font-size:15px;line-height:1.6">' . $inner . '</div>'
            . '<p style="margin-top:24px;font-size:12px;color:#6B7280">If you did not request this email you can safely ignore it.</p>'
            . '</div></body></html>';
    }
}
