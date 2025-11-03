<?php
// Serve riot.txt as strict plain text for verification
// Ensures: 200 on HEAD, exact body on GET, UTF-8 without BOM/newlines, proper Content-Type
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

$path = __DIR__ . '/riot.txt';
if (!is_file($path)) { http_response_code(404); exit; }

$raw = file_get_contents($path);
if ($raw === false) { http_response_code(500); exit; }

// Strip UTF-8 BOM if present
if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) { $raw = substr($raw, 3); }

// Trim whitespace/newlines
$code = trim($raw);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($code));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo $code;
}
exit;
