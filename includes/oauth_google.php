<?php
declare(strict_types=1);

/**
 * Google Sign-In (OAuth 2.0 authorization-code flow).
 *
 * Two endpoints drive this:
 *   /api/oauth_google_start.php    — builds the Google authorize URL,
 *                                    drops a signed CSRF `state` in the
 *                                    session, redirects the browser.
 *   /api/oauth_google_callback.php — receives ?code=… & ?state=… back,
 *                                    verifies state, exchanges the code
 *                                    for tokens, fetches the userinfo
 *                                    profile, and finds-or-creates the
 *                                    matching user, then logs them in.
 *
 * Account linking rule:
 *   1. If a user already has this exact google_sub → log them straight
 *      in (fastest path, no email lookup needed).
 *   2. Else if a user exists with the same verified email → attach the
 *      sub to that account and log them in. This is the common case for
 *      an existing member deciding to use Google going forward.
 *   3. Else → create a fresh account keyed on the Google email, log
 *      them in.
 *
 * We never trust Google's email as an identifier long-term (workspace
 * admins can rewrite user emails), which is why the sub is the durable
 * link. We DO trust email_verified=true on first-time link because it
 * makes the "one-tap continuation" experience work — otherwise every
 * existing member would end up with a second orphan account.
 */

if (!function_exists('oauth_google_config')) {

    function oauth_google_config(): array
    {
        // site_settings first, env fallback. Same pattern the mail /
        // Billplz configs use, so a single deploy can be steered
        // entirely from /admin without touching .env.
        $enabled = (bool) setting('oauth_google_enabled', false);
        $client  = trim((string) setting('oauth_google_client_id', (string) (getenv('GOOGLE_CLIENT_ID') ?: '')));
        $secret  = trim((string) setting('oauth_google_client_secret', (string) (getenv('GOOGLE_CLIENT_SECRET') ?: '')));

        // Public URL: reuse the same override that the Billplz block
        // uses, so a local dev override propagates to both. Without
        // one, config('app.url') is the source of truth.
        $publicUrlOverride = trim((string) setting('billplz_public_url', ''));
        if ($publicUrlOverride !== ''
            && stripos($publicUrlOverride, 'soundheal.local') === false
            && stripos($publicUrlOverride, 'localhost') === false) {
            $appUrl = rtrim($publicUrlOverride, '/');
        } else {
            $appUrl = rtrim((string) config('app.url'), '/');
        }

        return [
            'enabled'      => $enabled,
            'client_id'    => $client,
            'client_secret'=> $secret,
            'redirect_uri' => $appUrl . '/api/oauth_google_callback.php',
        ];
    }

    /**
     * True when the button should render on public login/register.
     * Requires the enable toggle AND both credentials.
     */
    function oauth_google_ready(): bool
    {
        $cfg = oauth_google_config();
        return $cfg['enabled'] && $cfg['client_id'] !== '' && $cfg['client_secret'] !== '';
    }

    /**
     * Build the Google authorize URL. `next` (optional) is where the
     * user should land AFTER a successful login — persisted in the
     * session next to `state` so we can trust it on the callback
     * without letting an attacker rewrite the destination via the URL.
     */
    function oauth_google_start_url(?string $next = null): string
    {
        $cfg = oauth_google_config();
        if ($cfg['client_id'] === '') {
            throw new RuntimeException('Google OAuth is not configured.');
        }

        // Random state binds the callback to this session (CSRF guard).
        $state = bin2hex(random_bytes(24));
        $_SESSION['_oauth_google'] = [
            'state'   => $state,
            'next'    => $next,
            'created' => time(),
        ];

        $params = [
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'include_granted_scopes' => 'true',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange the authorization code for tokens and pull the userinfo
     * profile. Returns the profile array (sub, email, name, picture, …)
     * or throws.
     */
    function oauth_google_handle_callback(string $code, string $state): array
    {
        $stored = $_SESSION['_oauth_google'] ?? null;
        unset($_SESSION['_oauth_google']); // one-shot state, always cleared

        if (!is_array($stored) || empty($stored['state'])) {
            throw new RuntimeException('No pending Google sign-in — please try again.');
        }
        if (!hash_equals((string) $stored['state'], $state)) {
            throw new RuntimeException('Google sign-in verification failed — please try again.');
        }
        if ((time() - (int) ($stored['created'] ?? 0)) > 600) {
            throw new RuntimeException('This Google sign-in link expired — please try again.');
        }

        $cfg = oauth_google_config();
        if ($cfg['client_id'] === '' || $cfg['client_secret'] === '') {
            throw new RuntimeException('Google sign-in is not configured on this site.');
        }

        // 1) Token exchange.
        $tokenPayload = http_build_query([
            'code'          => $code,
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]);
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $tokenPayload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        $code_http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        curl_close($ch);
        if ($code_http < 200 || $code_http >= 300 || $body === false) {
            error_log('[oauth_google] token exchange failed HTTP ' . $code_http . ' | ' . $curlErr . ' | ' . substr((string) $body, 0, 500));
            throw new RuntimeException('Google sign-in failed while exchanging the code. Please try again.');
        }
        $tokens = json_decode((string) $body, true);
        if (!is_array($tokens) || empty($tokens['access_token'])) {
            throw new RuntimeException('Google returned an unexpected response — please try again.');
        }

        // 2) Userinfo fetch. Using the v3 userinfo endpoint gives us
        // {sub, email, email_verified, name, given_name, family_name,
        //  picture, locale} — everything we need without also decoding
        // the id_token ourselves.
        $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokens['access_token']],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        $code_http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code_http < 200 || $code_http >= 300 || $body === false) {
            error_log('[oauth_google] userinfo failed HTTP ' . $code_http);
            throw new RuntimeException('Google sign-in failed while reading your profile. Please try again.');
        }
        $profile = json_decode((string) $body, true);
        if (!is_array($profile) || empty($profile['sub'])) {
            throw new RuntimeException('Google sign-in returned no profile — please try again.');
        }
        return [
            'profile' => $profile,
            'next'    => is_string($stored['next'] ?? null) ? $stored['next'] : null,
        ];
    }

    /**
     * Match a Google profile to a local user (create if needed) and
     * log them in via session. Returns the user id on success.
     *
     * Rules:
     *   - google_sub match wins (fast path).
     *   - email match on a verified Google email → attach sub + login.
     *   - otherwise create a fresh account.
     */
    function oauth_google_login_from_profile(array $profile): int
    {
        $sub    = (string) $profile['sub'];
        $email  = strtolower(trim((string) ($profile['email'] ?? '')));
        $name   = (string) ($profile['name'] ?? '');
        $picture= (string) ($profile['picture'] ?? '');
        $emailVerified = ($profile['email_verified'] ?? false) === true
                      || ($profile['email_verified'] ?? '') === 'true';

        if ($sub === '') {
            throw new RuntimeException('Google returned no account identifier.');
        }

        // 1. Existing google_sub link.
        $stmt = db()->prepare('SELECT id FROM users WHERE google_sub = :s LIMIT 1');
        $stmt->execute([':s' => $sub]);
        $userId = (int) ($stmt->fetchColumn() ?: 0);

        if ($userId > 0) {
            // Refresh the snapshot fields but don't overwrite full_name
            // if the user has already personalised it beyond what Google
            // has (defensive: keep any manual profile edits).
            if ($picture !== '') {
                db()->prepare('UPDATE users SET google_avatar_url = :p WHERE id = :id')
                    ->execute([':p' => $picture, ':id' => $userId]);
            }
            _oauth_google_finalise_login($userId);
            return $userId;
        }

        // 2. Email match — attach sub to the existing account. Only
        // when Google says the email is verified, else we could hijack
        // an account belonging to a real member by pretending to be
        // them at an unverified free-mail address.
        if ($email !== '' && $emailVerified) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId > 0) {
                db()->prepare(
                    'UPDATE users SET google_sub = :s, google_avatar_url = :p WHERE id = :id'
                )->execute([':s' => $sub, ':p' => $picture !== '' ? $picture : null, ':id' => $userId]);
                audit_log('oauth.google.link', 'users', $userId, ['email' => $email]);
                _oauth_google_finalise_login($userId);
                return $userId;
            }
        }

        // 3. Brand-new account. Unusable password (like the guest-
        // booking path) — they will always sign in via Google going
        // forward, or claim a password via "forgot password".
        if ($email === '') {
            throw new RuntimeException('Google did not share an email — please try a different sign-in.');
        }
        $randomPass = bin2hex(random_bytes(24));
        db()->prepare(
            'INSERT INTO users
                (role_id, full_name, email, password_hash, google_sub, google_avatar_url, status)
             VALUES (3, :name, :email, :hash, :sub, :pic, "active")'
        )->execute([
            ':name'  => $name !== '' ? format_name($name) : 'Friend',
            ':email' => $email,
            ':hash'  => password_hash($randomPass, PASSWORD_DEFAULT),
            ':sub'   => $sub,
            ':pic'   => $picture !== '' ? $picture : null,
        ]);
        $userId = (int) db()->lastInsertId();
        audit_log('oauth.google.register', 'users', $userId, ['email' => $email]);

        // Kick off a gentle welcome mail — same one the normal signup
        // sends — so the sanctuary email lands even for Google-first
        // members.
        if (function_exists('send_mail')) {
            send_mail($email, $name !== '' ? $name : 'friend', 'Welcome to ' . brand_name(),
                'welcome', ['full_name' => $name !== '' ? $name : 'friend']);
        }

        _oauth_google_finalise_login($userId);
        return $userId;
    }

    /**
     * Small wrapper: rotate session id, populate the user session, and
     * stamp last_login_at. Mirrors what attempt_login() does after a
     * password verify so the rest of the app doesn't need to know how
     * the sign-in happened.
     */
    function _oauth_google_finalise_login(int $userId): void
    {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $userId]);
        if (!function_exists('login_user_by_id') || !login_user_by_id($userId)) {
            throw new RuntimeException('Sign-in succeeded but we could not open your sanctuary. Please try again.');
        }
        audit_log('login.google', 'users', $userId);
    }
}
