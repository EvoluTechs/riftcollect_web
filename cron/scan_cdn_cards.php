<?php
// Scan the Riftbound public CDN to discover card images and seed the local cache.
// Works without official API: tries ranges per set, and records cards that exist.
// Can be executed from browser or CLI (OVH cron friendly). Outputs text/plain.
//
// Config via env or URL params:
// - RC_CDN_BASE   (default: https://cdn.rgpub.io/public/live/map/riftbound/latest)
// - RC_CDN_SETS   Comma-separated set codes (default: OGN)
// - RC_CDN_RANGE  Range like 1-500 or list like 1,2,3,10-20 (default: 1-500)
// - RC_CDN_DELAY_MS polite delay between requests (default: 100)
// - RC_CDN_EXT    filename of asset (default: full-desktop.jpg)
//
// URL overrides example:
// /cron/scan_cdn_cards.php?sets=OGN&range=1-400&ext=full-desktop.jpg

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../storage/logs/scan_cdn_cards.log');

require_once __DIR__ . '/../inc/Config.php';
require_once __DIR__ . '/../inc/Database.php';

use RiftCollect\Config;
use RiftCollect\Database;

Config::init();
Database::instance();

$BASE = getenv('RC_CDN_BASE') ?: 'https://cdn.rgpub.io/public/live/map/riftbound/latest';
$SETS = getenv('RC_CDN_SETS') ?: 'OGN';
$RANGE = getenv('RC_CDN_RANGE') ?: '1-500';
$DELAY = (int)(getenv('RC_CDN_DELAY_MS') ?: '100');
$EXT = getenv('RC_CDN_EXT') ?: 'full-desktop.jpg';
$RESCAN = (int)(getenv('RC_CDN_RESCAN') ?: '0');

// URL overrides
if (isset($_GET['sets'])) $SETS = (string)$_GET['sets'];
if (isset($_GET['range'])) $RANGE = (string)$_GET['range'];
if (isset($_GET['ext'])) $EXT = (string)$_GET['ext'];
if (isset($_GET['delay'])) $DELAY = max(0, (int)$_GET['delay']);
if (isset($_GET['rescan'])) $RESCAN = (int)$_GET['rescan'] ? 1 : 0;

function parse_range(string $expr): array {
    // supports: 1-10, 5, 20-25, comma separated
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

function head_status(string $url): int {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (RiftCollect CDN Scanner)'
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
    // Fallback without cURL: simple GET with minimal headers
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "User-Agent: Mozilla/5.0 (RiftCollect CDN Scanner)\r\nAccept: */*\r\n",
        ]
    ];
    $ctx = stream_context_create($opts);
    $fp = @fopen($url, 'r', false, $ctx);
    if ($fp === false) return 0;
    // Try to get response headers
    $meta = stream_get_meta_data($fp);
    @fclose($fp);
    if (!empty($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
        foreach ($meta['wrapper_data'] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int)$m[1];
                return $code;
            }
        }
    }
    return 0;
}

$CANDIDATE_JSON = ['metadata.json','index.json','card.json','data.json'];

function fetch_json(string $url): ?array {
    // Try with cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (RiftCollect CDN Scanner)',
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($res)) {
            $j = json_decode($res, true);
            if (is_array($j)) return $j; 
        }
    }
    // Fallback streams
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 20,
        'header' => "User-Agent: Mozilla/5.0 (RiftCollect CDN Scanner)\r\nAccept: application/json\r\n",
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return null;
    $j = json_decode($res, true);
    return is_array($j) ? $j : null;
}

function try_card_metadata(string $baseCardUrl, array $candidates): ?array {
    foreach ($candidates as $f) {
        $u = rtrim($baseCardUrl, '/') . '/' . $f;
        $j = fetch_json($u);
        if ($j) return $j;
    }
    return null;
}

$setList = array_values(array_filter(array_map('trim', explode(',', $SETS))));
$numbers = parse_range($RANGE);

try {
    $pdo = Database::instance();
} catch (Throwable $e) {
    echo "DB ERROR: ".$e->getMessage()."\n";
    error_log('[scan_cdn_cards] DB ERROR: '.$e->getMessage());
    http_response_code(500);
    exit;
}

try {
    $ins = $pdo->prepare('REPLACE INTO cards_cache (id,name,rarity,set_code,image_url,data_json,updated_at) VALUES (?,?,?,?,?,?,?)');
    // Track attempted URLs to avoid re-scanning
    $pdo->exec('CREATE TABLE IF NOT EXISTS cdn_scan_urls (
        url TEXT PRIMARY KEY,
        status TEXT,
        http_code INTEGER,
        last_checked INTEGER NOT NULL,
        notes TEXT
    )');
    $selUrl = $pdo->prepare('SELECT status,http_code,last_checked FROM cdn_scan_urls WHERE url = ?');
    $upsertUrl = $pdo->prepare('REPLACE INTO cdn_scan_urls (url,status,http_code,last_checked,notes) VALUES (?,?,?,?,?)');
} catch (Throwable $e) {
    echo "DB PREPARE ERROR: ".$e->getMessage()."\n";
    error_log('[scan_cdn_cards] DB PREPARE ERROR: '.$e->getMessage());
    http_response_code(500);
    exit;
}

$found = 0; $tried = 0; $start = time();

echo "Scanning CDN...\n";

echo "Base: $BASE\nSets: ".implode(',', $setList)."\nRange: $RANGE (".count($numbers)." candidates)\nAsset: $EXT\nDelay: {$DELAY}ms\nRescan: ".($RESCAN? 'yes':'no')."\n\n";

try {
    foreach ($setList as $set) {
        foreach ($numbers as $n) {
            $tried++;
            $num = str_pad((string)$n, 3, '0', STR_PAD_LEFT);
            $id = $set . '-' . $num;
            $cardBase = rtrim($BASE, '/') . '/' . rawurlencode($set) . '/cards/' . rawurlencode($id);
            $url = $cardBase . '/' . rawurlencode($EXT);
            // Skip if URL already scanned and rescan disabled
            $already = false; $prev = null;
            if (!$RESCAN) {
                $selUrl->execute([$url]);
                $prev = $selUrl->fetch();
                if ($prev) {
                    $already = true;
                }
            }
            if ($already) {
                echo "S"; // skipped
            } else {
                $code = head_status($url);
                $status = ($code >= 200 && $code < 300) ? 'found' : (($code === 403) ? 'denied' : (($code === 404) ? 'missing' : 'other'));
                $upsertUrl->execute([$url, $status, $code, time(), $id]);
                $ok = ($code >= 200 && $code < 300);
                if ($ok) {
                $now = time();
                // Try to get metadata JSON if exposed by CDN (not guaranteed)
                $meta = try_card_metadata($cardBase, $CANDIDATE_JSON) ?: [];
                $name = (string)($meta['name'] ?? $id);
                $rarity = (string)($meta['rarity'] ?? ($meta['rarityTier'] ?? '')) ?: null;
                $desc = (string)($meta['text'] ?? $meta['description'] ?? '');
                $payload = [
                    'source' => 'rgpub-cdn',
                    'url' => $url,
                    'asset' => $EXT,
                    'discovered_at' => $now,
                    'meta' => $meta,
                ];
                $ins->execute([$id, $name, $rarity, $set, $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $now]);
                $found++;
                    echo "FOUND: $id -> $url\n";
                } else {
                    if ($code === 403) { echo "D"; }
                    elseif ($code === 404 || $code === 0) { echo "."; }
                    else { echo "(".$code.")"; }
                }
            }
            if ($DELAY > 0) usleep($DELAY * 1000);
        }
        echo "\n";
    }
} catch (Throwable $e) {
    echo "\nRUNTIME ERROR: ".$e->getMessage()."\n";
    error_log('[scan_cdn_cards] RUNTIME ERROR: '.$e->getMessage());
    http_response_code(500);
    exit;
}

$dur = time() - $start;
echo "\nDone. Tried: $tried, Found: $found, Duration: {$dur}s\n";
