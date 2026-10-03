<?php
declare(strict_types=1);

/**
 * Lightweight per-key rate limiter using files in /logs/throttle/.
 * Good enough for a single-server SME deployment without Redis.
 *
 * throttle('login:' . $email, 5, 60) → returns false if 5 hits in 60 sec.
 */
function throttle(string $key, int $max, int $windowSec): bool
{
    $dir = SH_ROOT . '/logs/throttle';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return true;
    }
    $file = $dir . '/' . sha1($key);
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $raw  = @file_get_contents($file);
        $hits = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - $windowSec));
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

function throttle_reset(string $key): void
{
    $file = SH_ROOT . '/logs/throttle/' . sha1($key);
    if (is_file($file)) @unlink($file);
}

function client_ip(): string
{
    $forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forward) {
        $first = trim(explode(',', $forward)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
