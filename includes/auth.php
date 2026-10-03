<?php
declare(strict_types=1);

/**
 * Authentication helpers.
 */

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $update->execute([
            ':h'  => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $user['id'],
        ]);
    }
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'role_id'   => (int) $user['role_id'],
        'role'      => $user['role_name'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
    ];
    audit_log('login.success', 'users', (int) $user['id']);
    return true;
}

/**
 * Log a user in by id — no password check. Only for internal flows
 * that have already established trust some other way (e.g. guest
 * checkout after we created the user from their booking form).
 */
function login_user_by_id(int $userId): bool
{
    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.status = "active" LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) return false;
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'role_id'   => (int) $user['role_id'],
        'role'      => $user['role_name'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
    ];
    audit_log('login.by_id', 'users', (int) $user['id']);
    return true;
}

function logout(): void
{
    if (current_user_id()) {
        audit_log('logout', 'users', current_user_id());
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login(string $redirectTo = '/public/login.php'): void
{
    if (!is_logged_in()) {
        flash('auth', 'Please sign in to continue.', 'info');
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect($redirectTo);
    }
}

function register_user(string $fullName, string $email, string $password, ?string $phone = null, ?int $trialDays = null): int
{
    $trialEndsAt = null;
    if ($trialDays !== null && $trialDays > 0) {
        $trialEndsAt = (new DateTimeImmutable('+' . $trialDays . ' days'))->format('Y-m-d H:i:s');
    }
    $stmt = db()->prepare(
        'INSERT INTO users (role_id, full_name, email, phone, password_hash, status, trial_ends_at)
         VALUES (3, :name, :email, :phone, :hash, "active", :trial)'
    );
    $stmt->execute([
        ':name'  => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':hash'  => password_hash($password, PASSWORD_DEFAULT),
        ':trial' => $trialEndsAt,
    ]);
    $id = (int) db()->lastInsertId();
    audit_log($trialDays ? 'register.trial' : 'register', 'users', $id, $trialDays ? ['trial_days' => $trialDays] : []);
    return $id;
}

/**
 * Guest-mode booking helper: return an existing user id for the email
 * or create a lightweight one so a first-time visitor can book without
 * running the full sign-up flow first. The account is real (they can
 * "forgot password" to claim it later) but starts with an unusable
 * random password and no trial.
 *
 * Idempotent by email so a returning guest who books again with the
 * same email lands on the same user row. Name / phone are refreshed
 * only when they were previously blank so we don't overwrite a value
 * the user set intentionally.
 *
 * Returns ['id' => int, 'created' => bool] so the caller can decide
 * whether to fire a "your account is set up, tap to sign in" email.
 */
function find_or_create_guest_user(string $email, string $fullName, ?string $phone = null): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please provide a valid email address.');
    }

    $stmt = db()->prepare('SELECT id, full_name, phone FROM users WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();

    if ($row) {
        // Only fill in missing fields — never overwrite what a returning
        // customer already set on their profile.
        $updates = [];
        $params  = [':id' => (int) $row['id']];
        if (($row['full_name'] ?? '') === '' && $fullName !== '') {
            $updates[] = 'full_name = :n';
            $params[':n'] = $fullName;
        }
        if (($row['phone'] ?? '') === '' && $phone !== null && $phone !== '') {
            $updates[] = 'phone = :p';
            $params[':p'] = $phone;
        }
        if ($updates) {
            db()->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id')
                ->execute($params);
        }
        return ['id' => (int) $row['id'], 'created' => false];
    }

    // Random, unusable password. Users claim the account via the
    // existing "forgot password" flow when they want to sign in.
    $randomPass = bin2hex(random_bytes(24));
    db()->prepare(
        'INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
         VALUES (3, :name, :email, :phone, :hash, "active")'
    )->execute([
        ':name'  => $fullName !== '' ? $fullName : 'Guest',
        ':email' => $email,
        ':phone' => $phone ?: null,
        ':hash'  => password_hash($randomPass, PASSWORD_DEFAULT),
    ]);
    $id = (int) db()->lastInsertId();
    audit_log('register.guest', 'users', $id, ['email' => $email]);
    return ['id' => $id, 'created' => true];
}

/**
 * True when the user has an active membership OR an unexpired trial.
 */
function user_has_member_access(?int $userId = null): bool
{
    $userId = $userId ?? current_user_id();
    if (!$userId) return false;

    $hasMembership = false;
    try {
        $stmt = db()->prepare("SELECT 1 FROM memberships WHERE user_id = :u AND status = 'active' LIMIT 1");
        $stmt->execute([':u' => $userId]);
        $hasMembership = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[auth] membership lookup failed: ' . $e->getMessage());
    }
    if ($hasMembership) return true;

    // Trial column may not exist yet on older deployments — degrade gracefully.
    try {
        $stmt = db()->prepare("SELECT trial_ends_at FROM users WHERE id = :u");
        $stmt->execute([':u' => $userId]);
        $expires = $stmt->fetchColumn();
        return $expires && strtotime((string) $expires) > time();
    } catch (Throwable $e) {
        error_log('[auth] trial column missing — assuming no trial: ' . $e->getMessage());
        return false;
    }
}
