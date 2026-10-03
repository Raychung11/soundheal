<?php
/**
 * WhatsApp / Evolution API webhook receiver.
 *
 * Verifies the shared secret, captures the inbound message into ai_conversations,
 * and forwards it to the AI concierge for a soft reply.
 *
 * Configure WA_BASE_URL, WA_API_KEY, WA_INSTANCE and WA_WEBHOOK_SECRET.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$cfg = config('ai.whatsapp');
$secret = $cfg['webhook_secret'] ?? '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if ($secret !== '' && !hash_equals($secret, (string) $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature.']);
    exit;
}

$raw = file_get_contents('php://input');
file_put_contents(SH_ROOT . '/logs/whatsapp.log',
    '[' . date('c') . '] ' . $raw . PHP_EOL, FILE_APPEND);

$payload = json_decode((string) $raw, true) ?? [];
$from = $payload['data']['key']['remoteJid'] ?? $payload['from'] ?? null;
$text = $payload['data']['message']['conversation']
      ?? $payload['data']['message']['extendedTextMessage']['text']
      ?? $payload['message']
      ?? '';

if (!$from || !$text) {
    echo json_encode(['ok' => true, 'note' => 'Nothing actionable.']);
    exit;
}

$sessionToken = 'wa_' . preg_replace('/[^a-z0-9]/i', '', $from);
$conv = db()->prepare("SELECT id FROM ai_conversations WHERE session_token = :t LIMIT 1");
$conv->execute([':t' => $sessionToken]);
$conversationId = (int) ($conv->fetchColumn() ?: 0);
if (!$conversationId) {
    db()->prepare("INSERT INTO ai_conversations (session_token, channel) VALUES (:t, 'whatsapp')")
        ->execute([':t' => $sessionToken]);
    $conversationId = (int) db()->lastInsertId();
}

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'user', :m)")
    ->execute([':c' => $conversationId, ':m' => $text]);

define('ARIA_CHAT_LIBRARY', true);
require_once __DIR__ . '/ai_chat.php';
$reply = ai_reply($conversationId, $text, null);

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'assistant', :m)")
    ->execute([':c' => $conversationId, ':m' => $reply]);

// Send the reply back via Evolution / WhatsApp provider, if configured.
if (!empty($cfg['base_url']) && !empty($cfg['api_key']) && !empty($cfg['instance'])) {
    $endpoint = rtrim($cfg['base_url'], '/') . '/message/sendText/' . urlencode($cfg['instance']);
    $body = json_encode([
        'number' => preg_replace('/[^0-9]/', '', (string) $from),
        'text'   => $reply,
    ]);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $cfg['api_key'],
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

echo json_encode(['ok' => true]);
