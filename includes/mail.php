<?php
declare(strict_types=1);

/**
 * Mail helper. Uses PHPMailer if vendor/autoload.php is present;
 * otherwise falls back to PHP's mail() so we still send something
 * during development. Templates live in includes/mail_templates.php.
 */

require_once __DIR__ . '/mail_templates.php';

/**
 * Send a templated email.
 *
 * @param string $to       Recipient email
 * @param string $toName   Recipient display name
 * @param string $subject  Subject line
 * @param string $template Template name (see mail_templates.php)
 * @param array  $vars     Template vars
 */
function send_mail(string $to, string $toName, string $subject, string $template, array $vars = []): bool
{
    $cfg = config('app.mail');
    $brandName = brand_name();
    [$html, $text] = render_mail_template($template, $vars + [
        'app_name' => $brandName,
        'app_url'  => config('app.url'),
        'year'     => date('Y'),
    ]);

    $autoload = SH_ROOT . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return send_via_phpmailer($to, $toName, $subject, $html, $text, $cfg);
        }
    }

    // Fallback: PHP mail() — best-effort during development.
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $cfg['from_name'], $cfg['from_address']),
        'Reply-To: ' . $cfg['from_address'],
    ];
    $sent = @mail($to, $subject, $html, implode("\r\n", $headers));
    if (!$sent) {
        error_log('[MAIL] Failed to send "' . $subject . '" to ' . $to);
    }
    return $sent;
}

function send_via_phpmailer(string $to, string $toName, string $subject, string $html, string $text, array $cfg): bool
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

        return $mailer->send();
    } catch (\Throwable $e) {
        error_log('[MAIL] PHPMailer error: ' . $e->getMessage());
        return false;
    }
}
