<?php
declare(strict_types=1);

/**
 * Render a calm, on-brand email. Returns [html, text].
 */
function render_mail_template(string $name, array $vars): array
{
    $renderers = [
        'welcome'         => 'mail_template_welcome',
        'password_reset'  => 'mail_template_password_reset',
        'verify_email'    => 'mail_template_verify_email',
        'booking_confirm'     => 'mail_template_booking_confirm',
        'booking_reminder_24h' => 'mail_template_booking_reminder_24h',
        'booking_reminder_2h'  => 'mail_template_booking_reminder_2h',
        'contact_received'=> 'mail_template_contact_received',
        'corporate_lead'  => 'mail_template_corporate_lead',
    ];
    $fn = $renderers[$name] ?? null;
    if (!$fn || !function_exists($fn)) {
        return [mail_layout('Hello', '<p>Sent from ' . e($vars['app_name'] ?? 'SoundHeal') . '.</p>'), 'Hello'];
    }
    return $fn($vars);
}

function mail_layout(string $preheader, string $bodyHtml): string
{
    $brand   = e(brand_name());
    $tagline = e((string) setting('company_tagline', (string) config('app.tagline')));
    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>{$brand}</title>
</head>
<body style="margin:0;padding:0;background:#0a1027;font-family:Georgia,'Cormorant Garamond',serif;color:#f6efe5;">
<span style="display:none;opacity:0;visibility:hidden;height:0;width:0;font-size:1px;color:#0a1027;">{$preheader}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a1027;padding:32px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="background:#0f172a;border:1px solid rgba(255,255,255,0.06);border-radius:18px;overflow:hidden;">
<tr><td style="padding:36px 36px 16px;">
<div style="font-family:Georgia,'Cormorant Garamond',serif;color:#c9a46a;font-size:28px;letter-spacing:0.04em;">{$brand}</div>
<div style="color:#e7d2a3;font-size:11px;letter-spacing:0.4em;text-transform:uppercase;margin-top:4px;">{$tagline}</div>
</td></tr>
<tr><td style="padding:8px 36px 36px;font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.7;color:#f6efe5;">
{$bodyHtml}
</td></tr>
<tr><td style="padding:24px 36px 32px;border-top:1px solid rgba(255,255,255,0.05);font-family:Inter,Arial,sans-serif;font-size:11px;color:rgba(246,239,229,0.45);line-height:1.6;">
This is not medical advice. Please consult qualified professionals for medical or mental-health concerns.
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}

function mail_template_welcome(array $v): array
{
    $name = e($v['full_name'] ?? 'friend');
    $url  = e($v['app_url']);
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:24px;color:#e7d2a3;margin:0 0 16px;">Welcome, {$name}.</p>
<p>Take a slow breath in… and out. Your sanctuary is open.</p>
<p>Inside, you'll find upcoming sessions, a curated audio library, and Aria — our calm wellness concierge — ready to listen whenever you'd like to begin.</p>
<p style="margin:28px 0;">
  <a href="{$url}/member/dashboard.php" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">Enter your sanctuary</a>
</p>
<p style="color:rgba(246,239,229,0.65);">With care,<br>The SoundHeal team</p>
HTML;
    $text = "Welcome, {$name}. Your SoundHeal sanctuary is open: {$url}/member/dashboard.php";
    return [mail_layout('Welcome to SoundHeal — your sanctuary is open.', $body), $text];
}

function mail_template_password_reset(array $v): array
{
    $name = e($v['full_name'] ?? 'friend');
    $link = e($v['reset_url'] ?? '#');
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">A gentle reset, {$name}.</p>
<p>Tap below to choose a new password. The link expires in 60 minutes.</p>
<p style="margin:24px 0;">
  <a href="{$link}" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">Reset password</a>
</p>
<p style="color:rgba(246,239,229,0.6);font-size:13px;">If you didn't request this, no action is needed — your account stays as it is.</p>
HTML;
    return [mail_layout('Reset your SoundHeal password.', $body), "Reset your password: {$link}"];
}

function mail_template_verify_email(array $v): array
{
    $link = e($v['verify_url'] ?? '#');
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">One soft step.</p>
<p>Tap to confirm your email so we can hold space for you properly.</p>
<p style="margin:24px 0;">
  <a href="{$link}" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">Confirm email</a>
</p>
HTML;
    return [mail_layout('Confirm your email.', $body), "Confirm your email: {$link}"];
}

function mail_template_booking_confirm(array $v): array
{
    $title = e($v['event_title'] ?? 'your session');
    $when  = e($v['starts_at'] ?? '');
    $loc   = e($v['location'] ?? 'Location TBA');
    $ref   = e($v['booking_ref'] ?? '');
    $url   = e($v['app_url']);
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">Your seat is held.</p>
<p><strong style="color:#e7d2a3;">{$title}</strong><br>{$when}<br><span style="color:rgba(246,239,229,0.65);">{$loc}</span></p>
<p style="color:rgba(246,239,229,0.6);font-size:13px;">Booking reference: {$ref}</p>
<p style="margin:24px 0;">
  <a href="{$url}/member/my_bookings.php" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">View your ticket</a>
</p>
HTML;
    return [mail_layout('Your SoundHeal seat is held.', $body), "Booking {$ref} confirmed: {$title} — {$when}"];
}

function mail_template_booking_reminder_24h(array $v): array
{
    $name  = e($v['name'] ?? 'friend');
    $title = e($v['event_title'] ?? 'your session');
    $when  = e($v['starts_at'] ?? '');
    $loc   = e($v['location'] ?? 'Location TBA');
    $ref   = e($v['booking_ref'] ?? '');
    $url   = e($v['app_url']);
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">Tomorrow, {$name}.</p>
<p>A gentle reminder — your seat is held for:</p>
<p><strong style="color:#e7d2a3;">{$title}</strong><br>{$when}<br><span style="color:rgba(246,239,229,0.65);">{$loc}</span></p>
<p style="color:rgba(246,239,229,0.6);font-size:13px;">Booking reference: {$ref}</p>
<p>Wear something comfortable. Arrive a few minutes early so we can settle you in without hurry.</p>
<p style="margin:24px 0;">
  <a href="{$url}/member/my_bookings.php" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">Show ticket</a>
</p>
<p style="color:rgba(246,239,229,0.65);">We look forward to holding space for you.</p>
HTML;
    $text = "Reminder — tomorrow: {$title} at {$when}, {$loc}. Booking {$ref}.";
    return [mail_layout("A gentle reminder — your session is tomorrow.", $body), $text];
}

function mail_template_booking_reminder_2h(array $v): array
{
    $title = e($v['event_title'] ?? 'your session');
    $when  = e($v['starts_at'] ?? '');
    $loc   = e($v['location'] ?? 'Location TBA');
    $ref   = e($v['booking_ref'] ?? '');
    $url   = e($v['app_url']);
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">See you soon.</p>
<p>Your session starts in about two hours.</p>
<p><strong style="color:#e7d2a3;">{$title}</strong><br>{$when}<br><span style="color:rgba(246,239,229,0.65);">{$loc}</span></p>
<p style="color:rgba(246,239,229,0.6);font-size:13px;">Booking reference: {$ref}</p>
<p>Take a slow breath. Head out with time to spare.</p>
<p style="margin:24px 0;">
  <a href="{$url}/member/my_bookings.php" style="display:inline-block;background:#c9a46a;color:#0a1027;padding:14px 28px;border-radius:999px;text-decoration:none;font-family:Inter,Arial,sans-serif;font-weight:500;">Open ticket</a>
</p>
HTML;
    $text = "Your session starts in about 2 hours: {$title} at {$when}, {$loc}. Booking {$ref}.";
    return [mail_layout("See you soon — your session is in about 2 hours.", $body), $text];
}

function mail_template_contact_received(array $v): array
{
    $name = e($v['name'] ?? 'friend');
    $body = <<<HTML
<p style="font-family:Georgia,'Cormorant Garamond',serif;font-size:22px;color:#e7d2a3;">Received with care, {$name}.</p>
<p>Your note has reached us. We'll respond within two working days.</p>
<p style="color:rgba(246,239,229,0.6);">In the meantime, breathe slowly and softly.</p>
HTML;
    return [mail_layout('We received your note.', $body), "Hi {$name}, we received your note and will reply within two working days."];
}

function mail_template_corporate_lead(array $v): array
{
    $company = e($v['company_name'] ?? '');
    $msg     = e($v['message'] ?? '');
    $body = <<<HTML
<p>New corporate inquiry from <strong>{$company}</strong>.</p>
<blockquote style="border-left:3px solid #c9a46a;padding-left:14px;color:rgba(246,239,229,0.8);">{$msg}</blockquote>
<p>Open the admin to triage: <a href="{$v['app_url']}/admin/corporate_leads.php" style="color:#e7d2a3;">/admin/corporate_leads.php</a></p>
HTML;
    return [mail_layout('New corporate inquiry.', $body), "New corporate inquiry from {$company}."];
}
