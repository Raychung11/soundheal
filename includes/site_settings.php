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
