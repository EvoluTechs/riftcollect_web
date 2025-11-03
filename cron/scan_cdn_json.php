<?php
// Scan all JSON files discoverable under the Riftbound CDN base (best-effort).
// Works without directory listings by probing common JSON filenames at known paths
// and per-card subfolders using set codes and index ranges.
//
// Intended for OVH shared hosting (can run via browser or CLI). Outputs text/plain.
// JSON responses are saved under storage/cdn_json/, mirroring the CDN path.
//
// Configuration via env or URL params:
// - RC_CDN_BASE     Base URL (default: https://cdn.rgpub.io/public/live/map/riftbound/latest)
// - RC_CDN_SETS     Comma-separated set codes (default: OGN)
// - RC_CDN_RANGE    Range like 1-500 or list 1,2,3,10-20 (default: 1-500)
// - RC_CDN_DELAY_MS Polite delay between requests in ms (default: 100)
// - RC_CDN_MAX      Stop after N JSON files found (default: 0 = no limit)
// - RC_CDN_PATHS    Extra relative paths (comma-separated) to try (e.g. "index.json,manifest.json,expansions/index.json")
// URL overrides example:
// /cron/scan_cdn_json.php?sets=OGN&range=1-400&delay=50&max=50
// /cron/scan_cdn_json.php?paths=index.json,manifest.json,versions.json

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../storage/logs/scan_cdn_json.log');

$BASE  = getenv('RC_CDN_BASE') ?: 'https://cdn.rgpub.io/public/live/map/riftbound/latest';
$SETS  = getenv('RC_CDN_SETS') ?: 'OGN';
$RANGE = getenv('RC_CDN_RANGE') ?: '1-500';
$DELAY = (int)(getenv('RC_CDN_DELAY_MS') ?: '100');
$MAX   = (int)(getenv('RC_CDN_MAX') ?: '0');
$EXTRA = getenv('RC_CDN_PATHS') ?: '';

// URL overrides
if (isset($_GET['base']))  $BASE = (string)$_GET['base'];
if (isset($_GET['sets']))  $SETS = (string)$_GET['sets'];
if (isset($_GET['range'])) $RANGE = (string)$_GET['range'];
if (isset($_GET['delay'])) $DELAY = max(0, (int)$_GET['delay']);
if (isset($_GET['max']))   $MAX = max(0, (int)$_GET['max']);
if (isset($_GET['paths'])) $EXTRA = (string)$_GET['paths'];
$DRY = isset($_GET['dry']) && ($_GET['dry'] === '1' || strtolower((string)$_GET['dry']) === 'true');

$storageRoot = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
$outRoot = $storageRoot . '/cdn_json';
if (!is_dir($outRoot)) {
    @mkdir($outRoot, 0777, true);
}

function parse_range(string $expr): array {
    $parts = array_map('trim', explode(',', $expr));
    $nums = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        if (preg_match('/^(\d+)-(\d+)$/', $p, $m)) {
            $a = (int)$m[1]; $b = (int)$m[2];
            if ($a > $b) { [$a,$b] = [$b,$a]; }
            for ($i=$a; $i <= $b; $i++) $nums[] = $i;
        } elseif (ctype_digit($p)) {
            $nums[] = (int)$p;
        }
    }
    $nums = array_values(array_unique($nums));
    sort($nums);
    return $nums;
}

function fetch_json(string $url, ?int &$code = null): ?string {
    // cURL preferred for better header control
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (RiftCollect JSON Scanner)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain;q=0.5, */*;q=0.1'
            ],
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($res)) {
            // Accept content-types that include json
            if (stripos($ct, 'json') !== false || $ct === '' /* some CDNs omit */) {
                return $res;
            }
        }
        return null;
    }
    // Streams fallback
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 20,
        'header' => "User-Agent: Mozilla/5.0 (RiftCollect JSON Scanner)\r\nAccept: application/json\r\n",
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; break; }
        }
    }
    if ($res === false) return null;
    return $res;
}

function save_content(string $root, string $base, string $url, string $content): string {
    // Compute relative path from base -> url
    $rel = $url;
    if (stripos($url, $base) === 0) {
        $rel = substr($url, strlen($base));
    } else {
        // try to compare path parts
        $u = parse_url($url, PHP_URL_PATH) ?? $url;
        $b = parse_url($base, PHP_URL_PATH) ?? $base;
        $rel = (stripos((string)$u, (string)$b) === 0) ? substr((string)$u, strlen((string)$b)) : $u;
    }
    $rel = ltrim((string)$rel, '/');
    $target = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($target);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    file_put_contents($target, $content);
    return $target;
}

$setList = array_values(array_filter(array_map('trim', explode(',', $SETS))));
$numbers = parse_range($RANGE);
$extraPaths = array_values(array_filter(array_map('trim', explode(',', $EXTRA))));

$TOP_LEVEL_GUESSES = [
    'index.json', 'manifest.json', 'data.json', 'catalog.json', 'versions.json', 'sitemap.json', 'cards.json', 'sets.json', 'expansions.json'
];
$SET_LEVEL_GUESSES = [
    'index.json', 'manifest.json', 'data.json', 'cards.json', 'expansions.json', 'sets.json', 'cards/index.json'
];
$CARD_JSON_FILES = ['metadata.json','index.json','card.json','data.json'];

$found = 0; $tried = 0; $start = time();

echo "Scanning JSON at base: $BASE\nSets: ".implode(',', $setList)."\nRange: $RANGE (".count($numbers)." candidates)\nDelay: {$DELAY}ms\nMax: ".($MAX ?: '∞')."\nDry: ".($DRY?'yes':'no')."\n\n";

// queue of candidate URLs
$candidates = [];
foreach ($TOP_LEVEL_GUESSES as $p) { $candidates[] = rtrim($BASE, '/') . '/' . $p; }
foreach ($extraPaths as $p) { $candidates[] = rtrim($BASE, '/') . '/' . ltrim($p, '/'); }
foreach ($setList as $set) {
    $baseSet = rtrim($BASE, '/') . '/' . rawurlencode($set);
    foreach ($SET_LEVEL_GUESSES as $p) { $candidates[] = $baseSet . '/' . $p; }
    foreach ($numbers as $n) {
        $num = str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $cardBase = $baseSet . '/cards/' . rawurlencode($set.'-'.$num);
        foreach ($CARD_JSON_FILES as $f) { $candidates[] = $cardBase . '/' . $f; }
    }
}

// de-duplicate while preserving order
$candidates = array_values(array_unique($candidates));

foreach ($candidates as $url) {
    $tried++;
    $code = 0;
    $res = fetch_json($url, $code);
    if (is_string($res)) {
        // Validate JSON
        $decoded = json_decode($res, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (!$DRY) {
                $target = save_content($outRoot, $BASE, $url, $res);
            }
            $found++;
            echo 'FOUND JSON: ' . $url . ($DRY ? " (dry)" : ' -> saved') . "\n";
            if ($MAX && $found >= $MAX) break;
        } else {
            // non-JSON or HTML error masked
            echo 'NON-JSON CONTENT: ' . $url . ' (HTTP ' . $code . ")\n";
        }
    } else {
        if ($code === 403) {
            echo 'DENIED: ' . $url . "\n";
        } elseif ($code === 404 || $code === 0) {
            echo '.'; // compact progress for not found
        } else {
            echo 'MISS (' . $code . '): ' . $url . "\n";
        }
    }
    if ($DELAY > 0) usleep($DELAY * 1000);
}

$dur = time() - $start;
echo "\nDone. Tried: $tried, Found JSON: $found, Duration: {$dur}s\nSaved under: $outRoot\n";
