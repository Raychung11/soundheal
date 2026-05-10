<?php
declare(strict_types=1);

/**
 * Single entry point all PHP files include first.
 * It loads config, opens the session, prepares CSRF and DB helpers.
 */

if (defined('SOUNDHEAL_BOOTED')) {
    return;
}
define('SOUNDHEAL_BOOTED', true);

// Locate root regardless of which subfolder includes this file.
define('SH_ROOT', dirname(__DIR__));

// Surface errors only in debug mode.
$appConfig = require SH_ROOT . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', SH_ROOT . '/logs/php-error.log');

// Make config accessible globally.
$GLOBALS['app_config']     = $appConfig;
$GLOBALS['db_config']      = require SH_ROOT . '/config/db.php';
$GLOBALS['payment_config'] = require SH_ROOT . '/config/payment.php';
$GLOBALS['ai_config']      = require SH_ROOT . '/config/ai.php';

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/role.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/throttle.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/marketing.php';
