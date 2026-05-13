<?php
declare(strict_types=1);

/**
 * Reads/writes the `site_settings` table.
 * Settings are cached for the request to avoid repeat queries.
 */

function settings(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $rows = db()->query('SELECT `key`, `value`, `value_type` FROM site_settings')->fetchAll();
        foreach ($rows as $r) {
            $cache[$r['key']] = setting_cast((string)($r['value'] ?? ''), $r['value_type']);
        }
    } catch (Throwable $e) {
        // Table may not exist yet pre-migration — return empty defaults.
    }
    return $cache;
}

function setting(string $key, $default = null)
{
    $all = settings();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_cast(string $raw, string $type)
{
    return match ($type) {
        'int'  => (int) $raw,
        'bool' => $raw === '1' || strtolower($raw) === 'true',
        'json' => $raw === '' ? null : (json_decode($raw, true) ?? null),
        default => $raw,
    };
}

function set_setting(string $key, $value, string $type = 'string'): void
{
    $stored = match ($type) {
        'int'  => (string)(int)$value,
        'bool' => !empty($value) ? '1' : '0',
        'json' => is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        default => (string) $value,
    };
    $stmt = db()->prepare(
        "INSERT INTO site_settings (`key`, `value`, `value_type`, `updated_by`)
         VALUES (:k, :v, :t, :u)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `value_type` = VALUES(`value_type`), `updated_by` = VALUES(`updated_by`)"
    );
    $stmt->execute([':k' => $key, ':v' => $stored, ':t' => $type, ':u' => current_user_id()]);
}

/**
 * Resolve a stored path (either a /uploads/... local path or a full URL)
 * to something the browser can load.
 */
function media_src(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return url($path);
}

/**
 * Brand display name. Prefers the new company_brand setting, falls back
 * to the older company_name (kept for backwards compatibility), then
 * config('app.name'). Use this everywhere the user-facing brand appears.
 */
function brand_name(): string
{
    $brand = trim((string) setting('company_brand', ''));
    if ($brand !== '') return $brand;
    $name = trim((string) setting('company_name', ''));
    if ($name !== '') return $name;
    return (string) config('app.name', 'SoundHeal');
}

/**
 * Resolved Billplz payment config — merges environment defaults with
 * admin-managed site_settings, so credentials can live in either place.
 * site_settings wins when populated; env stays as a fallback / dev seed.
 */
function payment_config(): array
{
    $env = $GLOBALS['payment_config'] ?? [];

    $sandbox = setting('billplz_sandbox', null);
    if ($sandbox === null) {
        $sandbox = (bool) ($env['sandbox'] ?? true);
    } else {
        $sandbox = (bool) $sandbox;
    }

    $apiKey       = trim((string) setting('billplz_api_key',       (string) ($env['api_key']       ?? '')));
    $collectionId = trim((string) setting('billplz_collection_id', (string) ($env['collection_id'] ?? '')));
    $xSignature   = trim((string) setting('billplz_x_signature',   (string) ($env['x_signature']   ?? '')));

    $appUrl       = rtrim((string) config('app.url'), '/');
    $callbackUrl  = $appUrl . '/api/billplz_webhook.php';
    $redirectUrl  = trim((string) setting('billplz_redirect_url', ''))
                    ?: ($env['redirect_url'] ?? $appUrl . '/member/my_bookings.php');

    return [
        'gateway'       => 'billplz',
        'sandbox'       => $sandbox,
        'api_base'      => $sandbox
            ? 'https://www.billplz-sandbox.com/api/v3'
            : 'https://www.billplz.com/api/v3',
        'api_key'       => $apiKey,
        'collection_id' => $collectionId,
        'x_signature'   => $xSignature,
        'callback_url'  => $callbackUrl,
        'redirect_url'  => $redirectUrl,
        'currency'      => 'MYR',
    ];
}
