<?php
declare(strict_types=1);

/**
 * Database configuration + PDO connection helper.
 *
 * Returns a singleton PDO instance via db() in includes/functions.php.
 */

return [
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => (int) (getenv('DB_PORT') ?: 3306),
    'database'  => getenv('DB_NAME') ?: 'soundheal',
    'username'  => getenv('DB_USER') ?: 'root',
    'password'  => getenv('DB_PASS') ?: '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
