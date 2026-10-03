<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string) $sent)) {
    http_response_code(419);
    echo json_encode(['error' => 'Stale session.']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true) ?? [];
$contentId = (int) ($body['content_id'] ?? 0);
$seconds   = max(0, (int) ($body['seconds'] ?? 0));
if (!$contentId) {
    echo json_encode(['ok' => false]);
    exit;
}

db()->prepare(
    "INSERT INTO content_plays (user_id, content_id, seconds_played) VALUES (:u, :c, :s)"
)->execute([':u' => current_user_id(), ':c' => $contentId, ':s' => $seconds]);

echo json_encode(['ok' => true]);
