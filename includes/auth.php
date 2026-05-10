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
 * True when the user has an active membership OR an unexpired trial.
 */
function user_has_member_access(?int $userId = null): bool
{
    $userId = $userId ?? current_user_id();
    if (!$userId) return false;
    $stmt = db()->prepare(
        "SELECT (
                  EXISTS(SELECT 1 FROM memberships WHERE user_id = :u AND status = 'active')
                  OR
                  (SELECT trial_ends_at IS NOT NULL AND trial_ends_at > NOW()
                     FROM users WHERE id = :u)
                ) AS has_access"
    );
    $stmt->execute([':u' => $userId]);
    return (bool) $stmt->fetchColumn();
}
