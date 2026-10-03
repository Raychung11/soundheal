<?php
declare(strict_types=1);

/**
 * Billplz payment gateway configuration.
 * Sandbox vs production is toggled by BILLPLZ_SANDBOX.
 *
 * The callback / redirect URLs here are env-level defaults only —
 * payment_config() (in includes/site_settings.php) overrides them at
 * runtime with the active public URL (admin override > APP_URL >
 * request auto-detect), so production transactions don't depend on
 * these literals being correct.
 */

$sandbox = filter_var(getenv('BILLPLZ_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$baseUrl = rtrim(getenv('APP_URL') ?: 'https://jaemiesoundbath.com', '/');

return [
    'gateway'     => 'billplz',
    'sandbox'     => $sandbox,
    'api_base'    => $sandbox
        ? 'https://www.billplz-sandbox.com/api/v3'
        : 'https://www.billplz.com/api/v3',
    'api_key'     => getenv('BILLPLZ_API_KEY') ?: '',
    'collection_id' => getenv('BILLPLZ_COLLECTION_ID') ?: '',
    'x_signature' => getenv('BILLPLZ_X_SIGNATURE') ?: '',
    'callback_url' => $baseUrl . '/api/billplz_webhook.php',
    'redirect_url' => $baseUrl . '/member/payment_thanks.php',
    'currency'    => 'MYR',
];
