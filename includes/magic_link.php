<?php
declare(strict_types=1);

/**
 * Magic-link (passwordless) sign-in.
 *
 * Flow:
 *   1. Visitor drops their email on /public/magic_link_request.php.
 *      magic_link_issue() creates or fetches a lightweight user row,
 *      writes a hashed one-time token, and emails a link containing
 *      the raw token.
 *   2. Visitor taps the link, which lands on
 *      /public/magic_link_verify.php. magic_link_verify_and_login()
 *      hashes the URL token, matches it, checks TTL + unused, marks
 *      the token used, and logs the user in.
 *
 * Tokens are:
 *   - 24 hex bytes of random (192 bits of entropy)
 *   - stored ONLY as sha256(token) — the raw token lives in the URL
 *     the user received; a DB dump alone cannot forge a login
 *   - one-use (used_at stamped on verify)
 *   - 30-minute lifetime
 *
 * Anti-abuse:
 *   - IP throttled: 5 requests / 15 min per IP
 *   - Email throttled: 3 requests / 15 min per address
 *   - Responses to the request page NEVER disclose whether the email
 *     exists (same "check your inbox" flash on both paths) so the
 *     endpoint isn't a user-enumeration oracle.
 */

// 30 minutes — bare-minimum time a slow inbox needs.
defined('MAGIC_LINK_TTL_SECONDS') || define('MAGIC_LINK_TTL_SECONDS', 1800);
// Rate limits (per 15-min window).
defined('MAGIC_LINK_IP_MAX')      || define('MAGIC_LINK_IP_MAX', 5);
defined('MAGIC_LINK_EMAIL_MAX')   || define('MAGIC_LINK_EMAIL_MAX', 3);
defined('MAGIC_LINK_WINDOW')      || define('MAGIC_LINK_WINDOW', 900);

if (!function_exists('magic_link_issue')) {

    /**
     * Create + send a magic link. Returns ['ok'=>true] on success or
     * ['ok'=>false, 'error'=>message] on validation failure. Rate limits
     * are treated as "ok" externally (silent so we don't leak state
     * to attackers) but logged server-side.
     *
     * NEVER return whether the email existed — the endpoint must not
     * become a user-enumeration oracle.
     */
    function magic_link_issue(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please share a valid email address.'];
        }

        // Rate limits — both by IP and by email. Silent on trip: we
        // pretend success so a brute-force script can't tell whether
        // it's being blocked or the email was accepted.
        $ip = function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (function_exists('throttle')) {
            if (!throttle('magic:ip:' . $ip, MAGIC_LINK_IP_MAX, MAGIC_LINK_WINDOW)) {
                if (function_exists('audit_log')) {
                    audit_log('magic_link.throttled_ip', null, null, ['ip' => $ip]);
                }
                return ['ok' => true];
            }
            if (!throttle('magic:em:' . $email, MAGIC_LINK_EMAIL_MAX, MAGIC_LINK_WINDOW)) {
                if (function_exists('audit_log')) {
                    audit_log('magic_link.throttled_email', null, null, ['email' => $email]);
                }
                return ['ok' => true];
            }
        }

        // Find-or-create the user. Same pattern the guest-booking flow
        // uses: unusable random password, active status, role_id=3
        // (member). If a member with this email already exists, we
        // just bind the token to their id.
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $userId = (int) ($stmt->fetchColumn() ?: 0);

        if ($userId === 0) {
            $randomPass = bin2hex(random_bytes(24));
            db()->prepare(
                'INSERT INTO users (role_id, full_name, email, password_hash, status)
                 VALUES (3, :name, :email, :hash, "active")'
            )->execute([
                ':name'  => 'friend',
                ':email' => $email,
                ':hash'  => password_hash($randomPass, PASSWORD_DEFAULT),
            ]);
            $userId = (int) db()->lastInsertId();
            if (function_exists('audit_log')) {
                audit_log('magic_link.register', 'users', $userId, ['email' => $email]);
            }
        }

        // 192 bits of entropy.
        $rawToken   = bin2hex(random_bytes(24));
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = (new DateTimeImmutable('+' . MAGIC_LINK_TTL_SECONDS . ' seconds'))
                        ->format('Y-m-d H:i:s');

        db()->prepare(
            "INSERT INTO login_tokens
                (user_id, email, purpose, token_hash, expires_at,
                 requested_ip, requested_ua)
             VALUES
                (:u, :e, 'magic_link', :h, :exp, :ip, :ua)"
        )->execute([
            ':u'   => $userId,
            ':e'   => $email,
            ':h'   => $tokenHash,
            ':exp' => $expiresAt,
            ':ip'  => $ip,
            ':ua'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        ]);

        // Fetch the display name for the greeting, if the user has one
        // beyond the "friend" default.
        $nameStmt = db()->prepare('SELECT full_name FROM users WHERE id = :id');
        $nameStmt->execute([':id' => $userId]);
        $displayName = trim((string) ($nameStmt->fetchColumn() ?: ''));
        if ($displayName === '' || strtolower($displayName) === 'friend') {
            $displayName = 'friend';
        }

        $link = url('/public/magic_link_verify.php?token=' . urlencode($rawToken));
        if (function_exists('send_mail')) {
            send_mail($email, $displayName,
                'Your sanctuary link',
                'magic_link',
                [
                    'full_name'  => $displayName,
                    'magic_url'  => $link,
                    'ttl_label'  => '30 minutes',
                ]);
        }

        return ['ok' => true];
    }

    /**
     * Verify a raw magic-link token from the URL. On success, logs the
     * user in and returns ['ok'=>true, 'user_id'=>int]. On failure
     * returns ['ok'=>false, 'error'=>reason]. Marks the token used
     * atomically so a leaked link cannot be replayed.
     */
    function magic_link_verify_and_login(string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !preg_match('/^[a-f0-9]{48}$/i', $rawToken)) {
            return ['ok' => false, 'error' => 'That sanctuary link looks incomplete.'];
        }
        $hash = hash('sha256', $rawToken);

        // Atomic claim: fetch + mark used inside a transaction so two
        // parallel clicks can't both succeed.
        db()->beginTransaction();
        try {
            $stmt = db()->prepare(
                "SELECT id, user_id, email, expires_at, used_at
                   FROM login_tokens
                  WHERE token_hash = :h AND purpose = 'magic_link'
                  LIMIT 1
                  FOR UPDATE"
            );
            $stmt->execute([':h' => $hash]);
            $row = $stmt->fetch();

            if (!$row) {
                db()->rollBack();
                return ['ok' => false, 'error' => 'This link is not valid — please request a new one.'];
            }
            if ($row['used_at'] !== null) {
                db()->rollBack();
                return ['ok' => false, 'error' => 'This link has already been used. Request a fresh one below.'];
            }
            if (strtotime((string) $row['expires_at']) < time()) {
                db()->rollBack();
                return ['ok' => false, 'error' => 'This link has expired. Request a fresh one below.'];
            }

            db()->prepare("UPDATE login_tokens SET used_at = NOW() WHERE id = :id")
                ->execute([':id' => (int) $row['id']]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[magic_link] verify failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Something went wrong. Please try again.'];
        }

        // The user_id foreign-key nulls on delete, so double-check the
        // account still exists before logging them in.
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            // Fallback: re-resolve by email (edge case: user_id was
            // NULL'd by an admin delete between issue and click).
            $u = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $u->execute([':e' => (string) $row['email']]);
            $userId = (int) ($u->fetchColumn() ?: 0);
        }
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'The account for this link no longer exists.'];
        }

        if (!function_exists('login_user_by_id') || !login_user_by_id($userId)) {
            return ['ok' => false, 'error' => 'Sign-in succeeded but we could not open your sanctuary. Please try again.'];
        }

        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $userId]);
        if (function_exists('audit_log')) {
            audit_log('login.magic_link', 'users', $userId);
        }
        return ['ok' => true, 'user_id' => $userId];
    }
}
