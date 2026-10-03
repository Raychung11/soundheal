<?php
declare(strict_types=1);

/**
 * Member-refer-member helpers.
 * - referral_code_for_user(): lazily generates and persists a 6-char code.
 * - referral_link_for_user(): full share URL.
 * - capture_referral_cookie(): called early on the registration flow to
 *   pick up ?ref= or REFERRAL_REF cookie.
 * - apply_referral_on_signup(): credits the referrer (extends trial).
 */

const REFERRAL_COOKIE = 'sh_ref';

function referral_code_for_user(int $userId): string
{
    $stmt = db()->prepare('SELECT referral_code FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $existing = (string) ($stmt->fetchColumn() ?: '');
    if ($existing !== '') return $existing;

    // Lazily mint one. Try a few times in the (very) unlikely event of a clash.
    for ($i = 0; $i < 5; $i++) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $check = db()->prepare('SELECT 1 FROM users WHERE referral_code = :c LIMIT 1');
        $check->execute([':c' => $code]);
        if (!$check->fetchColumn()) {
            $upd = db()->prepare('UPDATE users SET referral_code = :c WHERE id = :id');
            $upd->execute([':c' => $code, ':id' => $userId]);
            return $code;
        }
    }
    // Last-ditch: longer code.
    $code = strtoupper(bin2hex(random_bytes(6)));
    db()->prepare('UPDATE users SET referral_code = :c WHERE id = :id')
        ->execute([':c' => $code, ':id' => $userId]);
    return $code;
}

function referral_link_for_user(int $userId): string
{
    $code = referral_code_for_user($userId);
    return url('/public/register.php?ref=' . urlencode($code));
}

/**
 * Look up a code (case-insensitive) and return the referrer's user_id.
 */
function referrer_id_for_code(?string $code): ?int
{
    if (!$code) return null;
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $stmt = db()->prepare("SELECT id FROM users WHERE referral_code = :c AND status = 'active' LIMIT 1");
    $stmt->execute([':c' => $code]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

/**
 * If the visitor lands with ?ref=XXXX, set a 30-day cookie so the
 * attribution survives navigation and form errors.
 */
function capture_referral_cookie(): void
{
    if (empty($_GET['ref'])) return;
    $code = strtoupper(trim((string) $_GET['ref']));
    if (!preg_match('/^[A-Z0-9]{4,16}$/', $code)) return;
    setcookie(REFERRAL_COOKIE, $code, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => (bool) config('app.security.cookie_secure', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REFERRAL_COOKIE] = $code;
}

function get_referral_cookie(): ?string
{
    $code = $_COOKIE[REFERRAL_COOKIE] ?? null;
    if (!$code) return null;
    $code = strtoupper(trim((string) $code));
    return preg_match('/^[A-Z0-9]{4,16}$/', $code) ? $code : null;
}

function clear_referral_cookie(): void
{
    setcookie(REFERRAL_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REFERRAL_COOKIE]);
}

/**
 * Called from register flow once a new user has been inserted.
 * - Stores users.referred_by_user_id
 * - Inserts a referrals row (status 'signed_up')
 * - Extends BOTH the referrer's and the referee's trial by N days
 *   (configurable via site_settings.referral_signup_trial_days)
 */
function apply_referral_on_signup(int $newUserId, ?string $code): void
{
    $code = $code ?: get_referral_cookie();
    if (!$code) return;

    $referrerId = referrer_id_for_code($code);
    if (!$referrerId || $referrerId === $newUserId) return;

    try {
        $upd = db()->prepare('UPDATE users SET referred_by_user_id = :r WHERE id = :id AND referred_by_user_id IS NULL');
        $upd->execute([':r' => $referrerId, ':id' => $newUserId]);

        $rewardDays = max(0, (int) setting('referral_signup_trial_days', 7));

        $ins = db()->prepare(
            "INSERT IGNORE INTO referrals (referrer_user_id, referee_user_id, reward_type, reward_amount)
             VALUES (:r, :n, :type, :amt)"
        );
        $ins->execute([
            ':r'    => $referrerId,
            ':n'    => $newUserId,
            ':type' => $rewardDays > 0 ? 'trial_extend_' . $rewardDays . 'd' : null,
            ':amt'  => $rewardDays,
        ]);

        if ($rewardDays > 0) {
            // Extend (or start) trial for the referrer.
            extend_trial($referrerId, $rewardDays);
            // Mirror on the new user too.
            extend_trial($newUserId, $rewardDays);

            db()->prepare(
                "UPDATE referrals SET status = 'rewarded', rewarded_at = NOW()
                 WHERE referrer_user_id = :r AND referee_user_id = :n"
            )->execute([':r' => $referrerId, ':n' => $newUserId]);
        }

        audit_log('referral.signup', 'referrals', null, ['referrer' => $referrerId, 'referee' => $newUserId]);
    } catch (Throwable $e) {
        error_log('[referral] ' . $e->getMessage());
    }

    clear_referral_cookie();
}

/**
 * Extends (or seeds) a user's trial by $days. Caps at +180 days from now.
 */
function extend_trial(int $userId, int $days): void
{
    if ($days <= 0) return;
    try {
        $stmt = db()->prepare('SELECT trial_ends_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $current = $stmt->fetchColumn();
        $base = ($current && strtotime((string) $current) > time())
            ? new DateTimeImmutable((string) $current)
            : new DateTimeImmutable('now');
        $new = $base->modify('+' . $days . ' days');
        $cap = new DateTimeImmutable('+180 days');
        if ($new > $cap) $new = $cap;
        db()->prepare('UPDATE users SET trial_ends_at = :t WHERE id = :id')
            ->execute([':t' => $new->format('Y-m-d H:i:s'), ':id' => $userId]);
    } catch (Throwable $e) {
        error_log('[trial] extend failed: ' . $e->getMessage());
    }
}
