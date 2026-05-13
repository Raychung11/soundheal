<?php
declare(strict_types=1);

/**
 * Billplz payment gateway configuration.
 * Sandbox vs production is toggled by BILLPLZ_SANDBOX.
 */

$sandbox = filter_var(getenv('BILLPLZ_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN);

return [
    'gateway'     => 'billplz',
    'sandbox'     => $sandbox,
    'api_base'    => $sandbox
        ? 'https://www.billplz-sandbox.com/api/v3'
        : 'https://www.billplz.com/api/v3',
    'api_key'     => getenv('BILLPLZ_API_KEY') ?: '',
    'collection_id' => getenv('BILLPLZ_COLLECTION_ID') ?: '',
    'x_signature' => getenv('BILLPLZ_X_SIGNATURE') ?: '',
    'callback_url' => (getenv('APP_URL') ?: 'https://soundheal.local') . '/api/billplz_webhook.php',
    'redirect_url' => (getenv('APP_URL') ?: 'https://soundheal.local') . '/member/payment_thanks.php',
    'currency'    => 'MYR',
];
