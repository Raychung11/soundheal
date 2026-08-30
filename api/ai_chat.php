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

// When pulled in as a library (e.g. the WhatsApp webhook) we only want the
// ai_reply()/aria_openai_call() functions — not this HTTP endpoint's body,
// which would otherwise emit its own headers + JSON and parse the wrong
// request. Callers define ARIA_CHAT_LIBRARY before requiring this file.
if (!defined('ARIA_CHAT_LIBRARY')) {

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
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = (string) ($_SESSION['_csrf_token'] ?? '');
    if ($stored === '' || $sent === '' || !hash_equals($stored, $sent)) {
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

// Soft throttle so the public widget can't drive runaway OpenAI cost.
// Logged-in members get a higher ceiling than anonymous visitors.
$throttleKey = 'aria:chat:' . (is_logged_in() ? ('u' . current_user_id()) : ('ip' . client_ip()));
$ceiling     = is_logged_in() ? 40 : 20;
if (!throttle($throttleKey, $ceiling, 600)) {
    http_response_code(429);
    echo json_encode(['error' => 'You\'re reaching out a lot — please pause a moment and try again shortly.']);
    exit;
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

// Capture an early mood signal if the user opens with "I'm feeling X today.".
if (preg_match('/i\s+am\s+feeling\s+([a-z]+)|i\'?m\s+feeling\s+([a-z]+)/i', $message, $m)) {
    $detected = strtolower(($m[1] ?? '') ?: ($m[2] ?? ''));
    db()->prepare("UPDATE ai_conversations SET mood = COALESCE(mood, :mood) WHERE id = :id")
        ->execute([':mood' => $detected, ':id' => $conversationId]);
}

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'user', :m)")
    ->execute([':c' => $conversationId, ':m' => $message]);

$reply = ai_reply($conversationId, $message, $userId);

db()->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, 'assistant', :m)")
    ->execute([':c' => $conversationId, ':m' => $reply]);

echo json_encode(['reply' => $reply, 'session_token' => $sessionToken]);

} // end: !defined('ARIA_CHAT_LIBRARY')

function ai_reply(int $conversationId, string $message, ?int $userId = null): string
{
    $cfg = ai_config();
    $apiKey = $cfg['openai']['api_key'] ?? '';

    if (!$apiKey) {
        return aria_fallback($message);
    }

    $history = db()->prepare(
        "SELECT role, content FROM ai_messages WHERE conversation_id = :c ORDER BY id DESC LIMIT 10"
    );
    $history->execute([':c' => $conversationId]);
    $turns = array_reverse($history->fetchAll());

    $messages = [['role' => 'system', 'content' => aria_system_prompt()]];
    foreach ($turns as $t) {
        $messages[] = ['role' => $t['role'], 'content' => $t['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $useTools = !empty($cfg['tools_enabled']);
    $maxIters = max(1, (int) ($cfg['max_tool_calls'] ?? 4));

    for ($i = 0; $i < $maxIters; $i++) {
        $request = [
            'model'       => $cfg['openai']['model'],
            'messages'    => $messages,
            'temperature' => (float) $cfg['temperature'],
            'max_tokens'  => 600,
        ];
        if ($useTools) {
            $request['tools']       = aria_tools_schema();
            $request['tool_choice'] = 'auto';
        }

        $resp = aria_openai_call($cfg, $request);
        if (!$resp['ok']) {
            error_log('[AI] OpenAI error ' . $resp['code'] . ': ' . substr((string)$resp['body'], 0, 500));
            return aria_fallback($message);
        }
        $choice = $resp['data']['choices'][0]['message'] ?? null;
        if (!$choice) {
            return aria_fallback($message);
        }

        // No tool call → final reply.
        if (empty($choice['tool_calls'])) {
            return trim((string) ($choice['content'] ?? '')) ?: aria_fallback($message);
        }

        // Append the assistant's tool-call message verbatim, then resolve
        // each tool and append its result. OpenAI requires the assistant
        // tool-call message in history before each `tool` reply.
        $messages[] = [
            'role'       => 'assistant',
            'content'    => $choice['content'] ?? null,
            'tool_calls' => $choice['tool_calls'],
        ];
        foreach ($choice['tool_calls'] as $tc) {
            $name = (string) ($tc['function']['name'] ?? '');
            $args = json_decode((string) ($tc['function']['arguments'] ?? '{}'), true) ?? [];
            $result = aria_execute_tool($name, is_array($args) ? $args : [], $userId);
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => (string) $tc['id'],
                'name'         => $name,
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    // Loop budget exhausted — ask Aria to wrap up gracefully.
    return "I gathered what I could, but lost the thread for a moment. Could you tell me which one of those interests you most?";
}

function aria_openai_call(array $cfg, array $request): array
{
    $ch = curl_init($cfg['openai']['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($request, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($cfg['openai']['api_key'] ?? ''),
        ],
        CURLOPT_TIMEOUT        => 40,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $body ? json_decode((string) $body, true) : null;
    return [
        'ok'   => $code >= 200 && $code < 300 && is_array($data),
        'code' => (int) $code,
        'body' => $body,
        'data' => $data,
    ];
}
