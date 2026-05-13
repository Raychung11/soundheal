<?php
declare(strict_types=1);

/**
 * Shared utility functions: db(), config(), e(), url(), redirect(), flash(), etc.
 */

function config(string $key, $default = null)
{
    [$bucket, $rest] = array_pad(explode('.', $key, 2), 2, null);
    $map = [
        'app'     => $GLOBALS['app_config']     ?? [],
        'db'      => $GLOBALS['db_config']      ?? [],
        'payment' => $GLOBALS['payment_config'] ?? [],
        'ai'      => $GLOBALS['ai_config']      ?? [],
    ];
    $data = $map[$bucket] ?? [];
    if ($rest === null) {
        return $data;
    }
    foreach (explode('.', $rest) as $segment) {
        if (is_array($data) && array_key_exists($segment, $data)) {
            $data = $data[$segment];
        } else {
            return $default;
        }
    }
    return $data;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = $GLOBALS['db_config'];
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['driver'], $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
    );
    try {
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
    } catch (PDOException $e) {
        error_log('[DB] ' . $e->getMessage());
        if (config('app.debug')) {
            throw $e;
        }
        http_response_code(500);
        exit('Database connection unavailable.');
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path, int $code = 302): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, $code);
    exit;
}

function flash(string $key, ?string $message = null, string $type = 'info')
{
    if ($message === null) {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
    $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
}

function flash_render(): string
{
    if (empty($_SESSION['_flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['_flash'] as $key => $entry) {
        $type = e($entry['type'] ?? 'info');
        $msg  = e($entry['message'] ?? '');
        $html .= "<div class=\"flash flash-{$type}\" role=\"status\">{$msg}</div>";
    }
    $_SESSION['_flash'] = [];
    return $html;
}

function input(string $key, $default = null)
{
    if (array_key_exists($key, $_POST)) {
        return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
    }
    return $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Title-case a person's name: trims, collapses whitespace, capitalises
 * the first letter of every word, preserves apostrophes and hyphens
 * ("o'neill" → "O'Neill", "mary-jane" → "Mary-Jane"). Unicode-safe.
 */
function format_name(string $raw): string
{
    $clean = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    if ($clean === '') return '';
    return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Normalise a phone number to E.164-ish format. Local Malaysian numbers
 * starting with 0 get rewritten to +60…. Bare digits also get +60
 * prepended. Numbers already containing a + are kept as-is (only the
 * non-digit characters after the + are stripped).
 *
 * Returns null when the input has no digits.
 */
function normalize_phone(?string $raw, string $defaultCountryCode = '60'): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') return null;

    if ($raw[0] === '+') {
        $digits = preg_replace('/\D/', '', substr($raw, 1));
        return $digits === '' ? null : '+' . $digits;
    }

    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') return null;

    if ($digits[0] === '0') {
        $digits = $defaultCountryCode . substr($digits, 1);
    } elseif (!str_starts_with($digits, $defaultCountryCode)) {
        $digits = $defaultCountryCode . $digits;
    }
    return '+' . $digits;
}

function format_money(float $amount, string $currency = 'MYR'): string
{
    return $currency . ' ' . number_format($amount, 2);
}

function format_datetime(?string $datetime, string $format = 'd M Y, g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($datetime))->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text) ?? '');
    return $text === '' ? bin2hex(random_bytes(4)) : $text;
}

function audit_log(string $action, ?string $entity = null, ?int $entityId = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip_address, user_agent, metadata)
             VALUES (:uid, :action, :entity, :entity_id, :ip, :ua, :meta)'
        );
        $stmt->execute([
            ':uid' => current_user_id(),
            ':action' => $action,
            ':entity' => $entity,
            ':entity_id' => $entityId,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            ':meta' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[AUDIT] ' . $e->getMessage());
    }
}
