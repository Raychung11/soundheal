<?php
/**
 * AI Wellness Concierge endpoint.
 *
 * Accepts:  POST JSON { message, session_token? }
 * Returns:  JSON { reply, session_token }
 *
 * Persists each turn in ai_conversations / ai_messages.
 * Uses OpenAI Chat Completions when OPENAI_API_KEY is configured;
 * otherwise returns a soft, scripted fallback.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode((string) $raw, true) ?? [];
$message = trim((string)($body['message'] ?? ''));

// Soft CSRF for logged-in members; anonymous flow does not require it.
if (is_logged_in()) {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string) $sent)) {
        http_response_code(419);
        echo json_encode(['error' => 'Stale session.']);
        exit;
    }
}

if ($message === '') {
    echo json_encode(['error' => 'Empty message.']);
    exit;
}
if (strlen($message) > 2000) {
    $message = substr($message, 0, 2000);
}

$sessionToken = $body['session_token'] ?? ($_SESSION['ai_session'] ?? null);
$userId = current_user_id();

if (!$sessionToken) {
    $sessionToken = generate_token(16);
    db()->prepare("INSERT INTO ai_conversations (user_id, session_token, channel) VALUES (:u, :t, 'web')")
        ->execute([':u' => $userId, ':t' => $sessionToken]);
    $_SESSION['ai_session'] = $sessionToken;
}
$conv = db()->prepare("SELECT id FROM ai_conversations WHERE session_token = :t LIMIT 1");
$conv->execute([':t' => $sessionToken]);
$conversationId = (int) ($conv->fetchColumn() ?: 0);
if (!$conversationId) {
    db()->prepare("INSERT INTO ai_conversations (user_id, session_token, channel) VALUES (:u, :t, 'web')")
        ->execute([':u' => $userId, ':t' => $sessionToken]);
    $conversationId = (int) db()->lastInsertId();
}

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'user', :m)")
    ->execute([':c' => $conversationId, ':m' => $message]);

$reply = ai_reply($conversationId, $message);

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'assistant', :m)")
    ->execute([':c' => $conversationId, ':m' => $reply]);

echo json_encode(['reply' => $reply, 'session_token' => $sessionToken]);

function ai_reply(int $conversationId, string $message): string
{
    $cfg = config('ai');
    $apiKey = $cfg['openai']['api_key'] ?? '';

    if (!$apiKey) {
        return ai_fallback($message);
    }

    $history = db()->prepare(
        "SELECT role, content FROM ai_messages WHERE conversation_id = :c ORDER BY id DESC LIMIT 10"
    );
    $history->execute([':c' => $conversationId]);
    $turns = array_reverse($history->fetchAll());

    $messages = [['role' => 'system', 'content' => $cfg['persona']['system_prompt']]];
    foreach ($turns as $t) {
        $messages[] = ['role' => $t['role'], 'content' => $t['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $payload = json_encode([
        'model'       => $cfg['openai']['model'],
        'messages'    => $messages,
        'temperature' => 0.6,
        'max_tokens'  => 500,
    ]);

    $ch = curl_init($cfg['openai']['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 25,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $response) {
        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        if ($text !== '') {
            return trim($text);
        }
    }
    error_log('[AI] OpenAI error ' . $code . ': ' . substr((string)$response, 0, 500));
    return ai_fallback($message);
}

function ai_fallback(string $message): string
{
    return "Thank you for sharing that with me. Take a slow breath in… and out.\n\n"
         . "If you're looking for stillness, our Sound Bath is a gentle place to begin. "
         . "If your nervous system feels frayed, our Breathwork Journey can help you settle.\n\n"
         . "This is not medical advice. Please consult qualified professionals for medical or mental health concerns.";
}
