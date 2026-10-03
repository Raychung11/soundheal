<?php
declare(strict_types=1);

/**
 * Mail helper.
 *
 *   Order of preference for the transport:
 *     1. PHPMailer (if vendor/autoload.php is present)
 *     2. Built-in lightweight SMTP client (works without Composer)
 *     3. PHP mail() — last-ditch fallback
 *
 *   Configuration is read from site_settings first (admin-managed via
 *   /admin/mail_settings.php) and falls back to env / config/app.php.
 *
 *   Every send is recorded in the mail_log table so admin can debug
 *   delivery issues without grepping error logs.
 */

require_once __DIR__ . '/mail_templates.php';

function mail_settings(): array
{
    $envCfg = (array) config('app.mail');

    $val = static function (string $key, string $envKey) use ($envCfg) {
        $fromDb = trim((string) setting($key, ''));
        if ($fromDb !== '') return $fromDb;
        return (string) ($envCfg[$envKey] ?? '');
    };

    return [
        'driver'       => $val('mail_driver',       'driver'),
        'host'         => $val('mail_host',         'host'),
        'port'         => (int) ($val('mail_port',  'port') ?: 587),
        'username'     => $val('mail_username',     'username'),
        'password'     => $val('mail_password',     'password'),
        'encryption'   => $val('mail_encryption',   'encryption'),
        'from_address' => $val('mail_from_address', 'from_address'),
        'from_name'    => $val('mail_from_name',    'from_name'),
    ];
}

function send_mail(string $to, string $toName, string $subject, string $template, array $vars = []): bool
{
    $cfg = mail_settings();
    $brandName = brand_name();
    [$html, $text] = render_mail_template($template, $vars + [
        'app_name' => $brandName,
        'app_url'  => config('app.url'),
        'year'     => date('Y'),
    ]);

    // 1. PHPMailer (if installed via Composer).
    $autoload = SH_ROOT . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return _mail_send_via_phpmailer($to, $toName, $subject, $html, $text, $cfg, $template);
        }
    }

    // 2. Built-in lightweight SMTP client — works as long as PHP can
    //    open a socket to the configured host on the configured port.
    if ($cfg['host'] !== '' && $cfg['username'] !== '' && $cfg['password'] !== '') {
        return _mail_send_via_native_smtp($to, $toName, $subject, $html, $text, $cfg, $template);
    }

    // 3. Last-ditch: native PHP mail(). Surface which SMTP field is missing
    //    so the admin can see it in the log instead of a generic message.
    $missing = [];
    if ($cfg['host']     === '') $missing[] = 'SMTP host';
    if ($cfg['username'] === '') $missing[] = 'username';
    if ($cfg['password'] === '') $missing[] = 'password';
    $reason = $missing
        ? 'Missing ' . implode(' + ', $missing) . ' in /admin/mail_settings.php. PHP mail() is unreliable on shared hosting — fill these in and save.'
        : 'Hostinger shared often blocks mail() — configure SMTP credentials instead.';
    return _mail_send_via_mail($to, $toName, $subject, $html, $text, $cfg, $template, $reason);
}

function _mail_log(string $to, string $toName, string $subject, ?string $template, string $status, string $driver, ?string $error = null): void
{
    try {
        $stmt = db()->prepare(
            "INSERT INTO mail_log (to_email, to_name, subject, template, status, driver, error)
             VALUES (:e, :n, :s, :t, :st, :d, :err)"
        );
        $stmt->execute([
            ':e'   => $to,
            ':n'   => $toName ?: null,
            ':s'   => substr($subject, 0, 250),
            ':t'   => $template,
            ':st'  => $status,
            ':d'   => $driver,
            ':err' => $error,
        ]);
    } catch (Throwable $e) {
        // Table may not exist yet pre-migration — fall back to error_log.
        error_log('[mail_log] ' . $e->getMessage() . ' | ' . $to . ' | ' . $subject);
    }
}

function _mail_send_via_phpmailer(string $to, string $toName, string $subject, string $html, string $text, array $cfg, string $template): bool
{
    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $cfg['host'];
        $mailer->SMTPAuth   = $cfg['username'] !== '';
        $mailer->Username   = $cfg['username'];
        $mailer->Password   = $cfg['password'];
        $mailer->SMTPSecure = $cfg['encryption'];
        $mailer->Port       = $cfg['port'];
        $mailer->CharSet    = 'UTF-8';

        $mailer->setFrom($cfg['from_address'], $cfg['from_name']);
        $mailer->addReplyTo($cfg['from_address'], $cfg['from_name']);
        $mailer->addAddress($to, $toName);

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body    = $html;
        $mailer->AltBody = $text;

        $mailer->send();
        _mail_log($to, $toName, $subject, $template, 'sent', 'phpmailer-smtp');
        return true;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        error_log('[MAIL] PHPMailer error: ' . $msg);
        _mail_log($to, $toName, $subject, $template, 'failed', 'phpmailer-smtp', $msg);
        return false;
    }
}

function _mail_send_via_mail(string $to, string $toName, string $subject, string $html, string $text, array $cfg, string $template, ?string $reasonHint = null): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $cfg['from_name'], $cfg['from_address']),
        'Reply-To: ' . $cfg['from_address'],
    ];
    $sent = @mail($to, $subject, $html, implode("\r\n", $headers));
    _mail_log($to, $toName, $subject, $template, $sent ? 'sent' : 'failed', 'mail()',
        $sent ? null : ($reasonHint ?: 'PHP mail() returned false. Hostinger shared often blocks this — configure SMTP credentials instead.'));
    return $sent;
}

/**
 * Minimal RFC-5321 SMTP client. Handles plain, STARTTLS and direct-SSL
 * connections, AUTH LOGIN, and standard MAIL FROM / RCPT TO / DATA.
 * Returns true on 250 reply to the body, false otherwise. No deps.
 */
function _mail_send_via_native_smtp(string $to, string $toName, string $subject, string $html, string $text, array $cfg, string $template): bool
{
    $host = $cfg['host'];
    $port = $cfg['port'];
    $enc  = strtolower($cfg['encryption']);

    $errno = 0; $errstr = '';
    $transport = ($enc === 'ssl') ? 'ssl://' : '';
    $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
    if (!$sock) {
        $err = sprintf('SMTP connect to %s:%d failed (%d %s)', $host, $port, $errno, $errstr);
        error_log('[MAIL] ' . $err);
        _mail_log($to, $toName, $subject, $template, 'failed', 'native-smtp', $err);
        return false;
    }
    stream_set_timeout($sock, 12);

    $read = static function () use ($sock): string {
        $out = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) break;
            $out .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $out;
    };
    $write = static function (string $cmd) use ($sock): void { fwrite($sock, $cmd . "\r\n"); };

    try {
        $banner = $read();
        if (!preg_match('/^220/', $banner)) throw new RuntimeException('Bad banner: ' . trim($banner));

        $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $write('EHLO ' . $ehloHost);
        $ehlo = $read();
        if (!preg_match('/^250/', $ehlo)) throw new RuntimeException('EHLO failed: ' . trim($ehlo));

        if ($enc === 'tls') {
            $write('STARTTLS');
            $starttls = $read();
            if (!preg_match('/^220/', $starttls)) throw new RuntimeException('STARTTLS rejected: ' . trim($starttls));
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed (mismatched cert or unsupported PHP)');
            }
            $write('EHLO ' . $ehloHost);
            $ehlo2 = $read();
            if (!preg_match('/^250/', $ehlo2)) throw new RuntimeException('EHLO after STARTTLS failed: ' . trim($ehlo2));
        }

        // AUTH LOGIN
        if ($cfg['username'] !== '') {
            $write('AUTH LOGIN');
            $authResp = $read();
            if (!preg_match('/^334/', $authResp)) throw new RuntimeException('AUTH LOGIN refused: ' . trim($authResp));

            $write(base64_encode($cfg['username']));
            $userResp = $read();
            if (!preg_match('/^334/', $userResp)) throw new RuntimeException('Username refused: ' . trim($userResp));

            $write(base64_encode($cfg['password']));
            $passResp = $read();
            if (!preg_match('/^235/', $passResp)) throw new RuntimeException('Password refused: ' . trim($passResp));
        }

        $write('MAIL FROM:<' . $cfg['from_address'] . '>');
        $mf = $read();
        if (!preg_match('/^250/', $mf)) throw new RuntimeException('MAIL FROM failed: ' . trim($mf));

        $write('RCPT TO:<' . $to . '>');
        $rc = $read();
        if (!preg_match('/^25\d/', $rc)) throw new RuntimeException('RCPT TO failed: ' . trim($rc));

        $write('DATA');
        $dt = $read();
        if (!preg_match('/^354/', $dt)) throw new RuntimeException('DATA refused: ' . trim($dt));

        $boundary = 'sh_' . bin2hex(random_bytes(6));
        $headers  = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($cfg['from_name']) . '?= <' . $cfg['from_address'] . '>',
            'To: ' . ($toName !== '' ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $to . '>' : '<' . $to . '>'),
            'Reply-To: ' . $cfg['from_address'],
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        // Strip CR/LF tampering before joining; SMTP injection prevention.
        foreach ($headers as $h) {
            if (preg_match('/[\r\n]/', $h)) {
                throw new RuntimeException('Header injection detected; aborting.');
            }
        }

        $body  = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= str_replace("\r\n", "\n", $text) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= str_replace("\r\n", "\n", $html) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        // Dot-stuff lines beginning with "." per RFC.
        $body = preg_replace("/(^|\r?\n)\\./", "\$1..", $body);

        fwrite($sock, $body);
        fwrite($sock, "\r\n.\r\n");
        $final = $read();
        if (!preg_match('/^250/', $final)) throw new RuntimeException('Body refused: ' . trim($final));

        $write('QUIT');
        @fclose($sock);

        _mail_log($to, $toName, $subject, $template, 'sent', 'native-smtp');
        return true;
    } catch (\Throwable $e) {
        @fclose($sock);
        $err = $e->getMessage();
        error_log('[MAIL native-smtp] ' . $err);
        _mail_log($to, $toName, $subject, $template, 'failed', 'native-smtp', $err);
        return false;
    }
}
