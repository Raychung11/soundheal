<?php
declare(strict_types=1);

/**
 * Role-based access control helpers.
 */

function user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function has_role(string ...$roles): bool
{
    $current = user_role();
    return $current !== null && in_array($current, $roles, true);
}

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function require_staff_or_admin(): void
{
    require_role('admin', 'staff');
}
