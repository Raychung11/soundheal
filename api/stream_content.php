<?php
/**
 * Gated audio stream. Validates access then sends the file with Range support.
 * Files live under /uploads/content/ (deny rule via .htaccess) so they can only
 * be reached via this endpoint.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = (int) input('id', 0);
$stmt = db()->prepare(
    "SELECT * FROM wellness_content WHERE id = :id AND is_published = 1 LIMIT 1"
);
$stmt->execute([':id' => $id]);
$track = $stmt->fetch();
if (!$track) {
    http_response_code(404);
    exit('Not found.');
}

if ($track['access'] === 'premium') {
    if (!user_has_member_access()) {
        http_response_code(403);
        exit('Membership required.');
    }
}

$path = $track['file_path'] ?? '';
if (!$path) { http_response_code(404); exit('No file.'); }

// External URLs (e.g. CDN) — redirect.
if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
    redirect($path);
}

$abs = SH_ROOT . $path;
if (!is_file($abs) || !is_readable($abs)) {
    http_response_code(404);
    exit('File missing.');
}

$mime = match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
    'mp3'         => 'audio/mpeg',
    'm4a','mp4'   => 'audio/mp4',
    'wav'         => 'audio/wav',
    'ogg'         => 'audio/ogg',
    default       => 'application/octet-stream',
};

$size = filesize($abs);
$start = 0;
$end   = $size - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = (int) $m[1];
    if ($m[2] !== '') {
        $end = (int) $m[2];
    }
    if ($start > $end || $end >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

$fh = fopen($abs, 'rb');
if (!$fh) { http_response_code(500); exit('Could not open file.'); }
fseek($fh, $start);
$buffer = 8192;
$remaining = $length;
while ($remaining > 0 && !feof($fh)) {
    $chunk = fread($fh, min($buffer, $remaining));
    if ($chunk === false) break;
    echo $chunk;
    flush();
    $remaining -= strlen($chunk);
}
fclose($fh);
