<?php
// RiftCollect - CDN Scanner with Vision AI extraction
// This script scans Riot Games' public CDN for Riftbound card images,
// sends each discovered image to a vision-enabled LLM to extract structured
// JSON metadata, and stores it in the local database for later integration.
//
// Usage (browser or CLI):
//   /cron/scan_cdn_cards.php?sets=OGN&range=1-40&ext=full-desktop.jpg&llm=1
//
// Environment/URL parameters:
// - RC_CDN_BASE     Base CDN path (default: https://cdn.rgpub.io/public/live/map/riftbound/latest)
// - RC_CDN_SETS     Comma-separated set codes (default: OGN)
// - RC_CDN_RANGE    Range like 1-500 or list like 1,2,3,10-20 (default: 1-300)
// - RC_CDN_EXT      Image filename (default: full-desktop.jpg)
// - RC_CDN_DELAY_MS Delay between requests in ms (default: 150)
// - RC_IMG_FORCE    1 to overwrite saved images (default: 0)
// - RC_LLM_ENABLED  1 to enable AI extraction (default from Config, recommended 1)
// - RC_LLM_MODEL    Vision model (default from Config, e.g., gpt-4o-mini)
// - RC_LLM_MAX_CALLS Max AI calls per run (default from Config)
// - RC_LLM_BASE_URL OpenAI base URL (default from Config)
// - OPENAI_API_KEY  API key (from env or Config)

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../storage/logs/scan_cdn_cards.log');
@header('X-Accel-Buffering: no');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
if (function_exists('ob_implicit_flush')) { @ob_implicit_flush(true); }

function rc_flush(): void {
    echo ' ';
    if (function_exists('ob_flush')) { @ob_flush(); }
    @flush();
}

function rc_out(string $text, ?string $progressFile = null): void {
    // Echo to client (if still connected) and mirror to progress file
    echo $text;
    if ($progressFile && $progressFile !== '') {
        @file_put_contents($progressFile, $text, FILE_APPEND);
    }
    rc_flush();
}

require_once __DIR__ . '/../inc/Config.php';
require_once __DIR__ . '/../inc/Database.php';

use RiftCollect\Config;
use RiftCollect\Database;

Config::init();
try {
    $pdo = Database::instance();
} catch (Throwable $e) {
    // Early DB connection failure — report clearly to the client and stop
    echo "RUNTIME ERROR: " . $e->getMessage() . "\n";
    @error_log('[scan_cdn_cards] DB init error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

$BASE = getenv('RC_CDN_BASE') ?: 'https://cdn.rgpub.io/public/live/map/riftbound/latest';
$SETS = getenv('RC_CDN_SETS') ?: 'OGN';
$RANGE = getenv('RC_CDN_RANGE') ?: '1-300';
$EXT   = getenv('RC_CDN_EXT') ?: 'full-desktop.jpg';
$DELAY = (int)(getenv('RC_CDN_DELAY_MS') ?: '150');
$IMG_FORCE = (int)(getenv('RC_IMG_FORCE') ?: '0');

// New runtime controls to avoid web timeouts
$MAX_SECONDS = (int)(getenv('RC_MAX_SECONDS') ?: (PHP_SAPI === 'cli' ? '0' : '25')); // 0 = unlimited
$MAX_ITEMS   = (int)(getenv('RC_MAX_ITEMS') ?: '0'); // 0 = unlimited (counted on found items)
$ASYNC       = (int)(getenv('RC_ASYNC') ?: '0'); // 1 = finish response and continue in background
// Scan only alternative variants (e.g., OGN-007a)
$ALT_ONLY    = (int)(getenv('RC_ALT_ONLY') ?: '0');

// Vision AI config (OpenAI compatible)
$LLM_ENABLED   = Config::$LLM_ENABLED ?? 1;
$LLM_MODEL     = Config::$LLM_MODEL ?? 'gpt-4o-mini';
$LLM_MAX_CALLS = Config::$LLM_MAX_CALLS ?? 50;
$LLM_BASE_URL  = Config::$LLM_BASE_URL ?? 'https://api.openai.com/v1';
$OPENAI_API_KEY = (Config::$OPENAI_API_KEY ?? '') ?: '';
$LLM_TIMEOUT   = (int)(getenv('RC_LLM_TIMEOUT') ?: (PHP_SAPI === 'cli' ? '60' : '30')); // default shorter on web
// AI retry/overwrite controls
$LLM_RETRY_ONLY = (int)(getenv('RC_LLM_RETRY_ONLY') ?: '0'); // 1 = only run AI for cards without AI data
$LLM_OVERWRITE  = (int)(getenv('RC_LLM_OVERWRITE') ?: '0');  // 1 = force re-run AI even if already present
// Optional progress mirror file (append-only). Relative to storage/logs by default.
$PROGRESS_FILE = '';

// URL overrides
// Also support CLI: php scan_cdn_cards.php sets=OGN range=1-40 llm=1
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    for ($i = 1; $i < count($argv); $i++) {
        if (strpos($argv[$i], '=') !== false) {
            [$k, $v] = explode('=', $argv[$i], 2);
            $_GET[$k] = $v;
        }
    }
}
if (isset($_GET['sets'])) $SETS = (string)$_GET['sets'];
if (isset($_GET['range'])) $RANGE = (string)$_GET['range'];
if (isset($_GET['ext'])) $EXT = (string)$_GET['ext'];
if (isset($_GET['delay'])) $DELAY = max(0, (int)$_GET['delay']);
if (isset($_GET['forceimg'])) $IMG_FORCE = (int)$_GET['forceimg'] ? 1 : 0;
if (isset($_GET['llm'])) $LLM_ENABLED = (int)$_GET['llm'] ? 1 : 0;
if (isset($_GET['llmModel'])) $LLM_MODEL = (string)$_GET['llmModel'];
if (isset($_GET['llmMax'])) $LLM_MAX_CALLS = max(0, (int)$_GET['llmMax']);
if (isset($_GET['llmRetryOnly'])) $LLM_RETRY_ONLY = (int)$_GET['llmRetryOnly'] ? 1 : 0;
if (isset($_GET['llmOverwrite'])) $LLM_OVERWRITE = (int)$_GET['llmOverwrite'] ? 1 : 0;
// New URL overrides
if (isset($_GET['maxSec'])) $MAX_SECONDS = max(0, (int)$_GET['maxSec']);
if (isset($_GET['limit'])) $MAX_ITEMS = max(0, (int)$_GET['limit']);
if (isset($_GET['async'])) $ASYNC = (int)$_GET['async'] ? 1 : 0;
if (isset($_GET['llmTimeout'])) $LLM_TIMEOUT = max(5, (int)$_GET['llmTimeout']);
if (isset($_GET['altOnly'])) $ALT_ONLY = (int)$_GET['altOnly'] ? 1 : 0;
// New: progress file support (mirror text output into a log file under storage/logs)
if (isset($_GET['progressFile'])) {
    $pf = trim((string)$_GET['progressFile']);
    // Security: allow only safe filename pattern under storage/logs
    if ($pf !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $pf)) {
        $baseDir = realpath(__DIR__ . '/../storage/logs');
        if ($baseDir !== false) {
            $PROGRESS_FILE = $baseDir . DIRECTORY_SEPARATOR . $pf;
        }
    }
}

// Output controls
$OUT = getenv('RC_OUT') ?: 'text'; // 'text' | 'json'
$PRINT_AI = (int)(getenv('RC_PRINT_AI') ?: '1');
$SAVE_AI = (int)(getenv('RC_SAVE_AI') ?: '0');
if (isset($_GET['out'])) $OUT = (string)$_GET['out'];
if (isset($_GET['printAI'])) $PRINT_AI = (int)$_GET['printAI'] ? 1 : 0;
if (isset($_GET['saveAI'])) $SAVE_AI = (int)$_GET['saveAI'] ? 1 : 0;
$OUT = strtolower(trim($OUT)) === 'json' ? 'json' : 'text';
if ($OUT === 'json') {
    // Switch header to JSON before any output
    header('Content-Type: application/json; charset=utf-8');
}

// If a progress file is requested, start an output buffer that tees all output to the file
if ($PROGRESS_FILE !== '') {
    $dir = dirname($PROGRESS_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    // Touch/clear file at start
    @file_put_contents($PROGRESS_FILE, "");
    if (function_exists('ob_start')) {
        ob_start(function ($chunk) use ($PROGRESS_FILE) {
            // Append chunk to progress file and also send to client
            @file_put_contents($PROGRESS_FILE, $chunk, FILE_APPEND);
            return $chunk;
        });
    }
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
    $meta = stream_get_meta_data($fp);
    @fclose($fp);
    if (!empty($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
        foreach ($meta['wrapper_data'] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) return (int)$m[1];
        }
    }
    return 0;
}

function ensure_dir(string $dir): void { if (!is_dir($dir)) @mkdir($dir, 0775, true); }

function map_ext_from_content_type(?string $ct, string $fallbackUrl): string {
    $ct = $ct ? strtolower(trim($ct)) : '';
    $map = [
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg',
        'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    ];
    if ($ct && isset($map[$ct])) return $map[$ct];
    if (preg_match('/\.([a-zA-Z0-9]{3,4})(?:\?|#|$)/', $fallbackUrl, $m)) {
        $e = strtolower($m[1]); if ($e === 'jpeg') $e = 'jpg'; return $e;
    }
    return 'jpg';
}

// --- Enum helpers for icon-based normalization ---
function rc_slug(string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    // Remove accents
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if (!is_string($s)) $s = '';
    $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
    $s = trim($s ?? '', '-');
    return $s ?? '';
}

function list_icon_keys(string $subdir): array {
    $dir = realpath(__DIR__ . '/../assets/img/' . $subdir);
    $out = [];
    if ($dir && is_dir($dir)) {
        $dh = opendir($dir);
        if ($dh) {
            while (($f = readdir($dh)) !== false) {
                if ($f === '.' || $f === '..') continue;
                if (!preg_match('/\.(png|webp|jpg|jpeg|gif|svg)$/i', $f)) continue;
                $key = preg_replace('/\.[^.]+$/', '', $f);
                $key = rc_slug($key);
                if ($key !== '') $out[$key] = $dir . DIRECTORY_SEPARATOR . $f;
            }
            closedir($dh);
        }
    }
    ksort($out);
    return array_keys($out);
}

function list_icon_map(string $subdir): array {
    $dir = realpath(__DIR__ . '/../assets/img/' . $subdir);
    $out = [];
    if ($dir && is_dir($dir)) {
        $dh = opendir($dir);
        if ($dh) {
            while (($f = readdir($dh)) !== false) {
                if ($f === '.' || $f === '..') continue;
                if (!preg_match('/\.(png|webp|jpg|jpeg|gif|svg)$/i', $f)) continue;
                $key = preg_replace('/\.[^.]+$/', '', $f);
                $key = rc_slug($key);
                if ($key !== '') $out[$key] = $dir . DIRECTORY_SEPARATOR . $f;
            }
            closedir($dh);
        }
    }
    ksort($out);
    return $out;
}

// --- Image-based rarity detection (heuristic) ---
function gd_load_image(string $path) {
    if (!function_exists('imagecreatefromstring')) return null;
    $data = @file_get_contents($path);
    if ($data === false) return null;
    $im = @imagecreatefromstring($data);
    return $im ?: null;
}

function gd_avg_rgb($im): array {
    $w = imagesx($im); $h = imagesy($im);
    $sumR = 0; $sumG = 0; $sumB = 0; $count = 0;
    // Sample every Nth pixel for speed on large images
    $stepX = max(1, (int)floor($w / 64));
    $stepY = max(1, (int)floor($h / 64));
    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = 0; $x < $w; $x += $stepX) {
            $col = imagecolorat($im, $x, $y);
            $r = ($col >> 16) & 0xFF; $g = ($col >> 8) & 0xFF; $b = $col & 0xFF;
            $sumR += $r; $sumG += $g; $sumB += $b; $count++;
        }
    }
    if ($count <= 0) return [0,0,0];
    return [ (int)round($sumR/$count), (int)round($sumG/$count), (int)round($sumB/$count) ];
}

function gd_crop($im, int $x, int $y, int $w, int $h) {
    $crop = imagecreatetruecolor($w, $h);
    imagecopy($crop, $im, 0, 0, $x, $y, $w, $h);
    return $crop;
}

function rgb_to_hsv(int $r, int $g, int $b): array {
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r,$g,$b); $min = min($r,$g,$b);
    $v = $max; $d = $max - $min;
    $s = $max == 0 ? 0 : ($d / $max);
    if ($d == 0) { $h = 0; }
    else {
        if ($max == $r) { $h = ($g - $b) / $d + ($g < $b ? 6 : 0); }
        elseif ($max == $g) { $h = ($b - $r) / $d + 2; }
        else { $h = ($r - $g) / $d + 4; }
        $h /= 6;
    }
    return [$h,$s,$v];
}

function hsv_distance(array $a, array $b): float {
    // Hue wrap-around aware distance with weights
    $dh = min(abs($a[0]-$b[0]), 1-abs($a[0]-$b[0])); // [0..0.5]
    $ds = abs($a[1]-$b[1]);
    $dv = abs($a[2]-$b[2]);
    // Heavier on hue, then saturation, lighter on value
    return sqrt( (3.0*$dh)**2 + (1.2*$ds)**2 + (0.6*$dv)**2 );
}

function hex_to_rgb(string $hex): ?array {
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) {
        $r = hexdec(str_repeat($h[0], 2));
        $g = hexdec(str_repeat($h[1], 2));
        $b = hexdec(str_repeat($h[2], 2));
        return [$r,$g,$b];
    }
    if (strlen($h) === 6) {
        $r = hexdec(substr($h,0,2));
        $g = hexdec(substr($h,2,2));
        $b = hexdec(substr($h,4,2));
        return [$r,$g,$b];
    }
    return null;
}

function svg_avg_rgb(string $path): ?array {
    $svg = @file_get_contents($path);
    if ($svg === false || $svg === '') return null;
    $sumR = 0.0; $sumG = 0.0; $sumB = 0.0; $sumW = 0.0;
    // Split by path tags to read attributes locally
    $parts = preg_split('/<path\s+/i', $svg);
    if (!$parts || count($parts) < 2) return null;
    array_shift($parts); // remove preamble
    foreach ($parts as $part) {
        $tag = substr($part, 0, strpos($part, '>') !== false ? strpos($part, '>') : strlen($part));
        if ($tag === '') continue;
        $fill = null; $opacity = 1.0;
        if (preg_match('/\bfill\s*=\s*"#([0-9a-fA-F]{3,6})"/i', $tag, $m)) {
            $fill = $m[1];
        }
        if ($fill === null) continue; // skip non-filled paths
        if (preg_match('/\bopacity\s*=\s*"([0-9]*\.?[0-9]+)"/i', $tag, $m)) {
            $opacity = max(0.0, min(1.0, (float)$m[1]));
        } elseif (preg_match('/style\s*=\s*"[^"]*opacity\s*:\s*([0-9]*\.?[0-9]+)/i', $tag, $m)) {
            $opacity = max(0.0, min(1.0, (float)$m[1]));
        }
        $rgb = hex_to_rgb($fill);
        if (!$rgb) continue;
        $w = max(0.01, $opacity); // ensure at least minimal weight
        $sumR += $rgb[0] * $w; $sumG += $rgb[1] * $w; $sumB += $rgb[2] * $w; $sumW += $w;
    }
    if ($sumW <= 0.0) return null;
    return [ (int)round($sumR/$sumW), (int)round($sumG/$sumW), (int)round($sumB/$sumW) ];
}

function detect_rarity_from_image(string $imagePath, array $iconsMap): string {
    if (!function_exists('imagecreatefromstring')) return '';
    $im = gd_load_image($imagePath);
    if (!$im) return '';
    $iw = imagesx($im); $ih = imagesy($im);
    if ($iw <= 0 || $ih <= 0) { imagedestroy($im); return ''; }

    // Compute average color of each rarity icon once
    $iconAvgs = [];
    foreach ($iconsMap as $key => $iconPath) {
        $ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $avg = svg_avg_rgb($iconPath);
            if ($avg) $iconAvgs[$key] = $avg;
        } else {
            $icon = gd_load_image($iconPath);
            if ($icon) {
                $iconAvgs[$key] = gd_avg_rgb($icon);
                imagedestroy($icon);
            }
        }
    }
    if (!$iconAvgs) { imagedestroy($im); return ''; }
    // Precompute HSV
    $iconHSV = [];
    foreach ($iconAvgs as $k => $rgb) { $iconHSV[$k] = rgb_to_hsv($rgb[0], $rgb[1], $rgb[2]); }

    // 1) Specialized bottom-center probes: rarity as a small dot centered at the very bottom
    $bcSizes = [0.045, 0.055, 0.065];
    $bcOffsets = [0.05, 0.07, 0.09, 0.11]; // how far above the bottom
    $bcCandidates = [];
    foreach ($bcSizes as $sr) {
        $bcSize = (int)max(6, round(min($iw, $ih) * $sr));
        $bcX = max(0, (int)round(($iw - $bcSize) / 2));
        foreach ($bcOffsets as $or) {
            $bcY = max(0, (int)round($ih - $bcSize - ($ih * $or)));
            $bcCandidates[] = [$bcX, $bcY, min($bcSize,$iw), min($bcSize,$ih)];
        }
    }
    // Heuristic: if any bottom-center sample has very low saturation, assume 'common'
    foreach ($bcCandidates as [$x,$y,$w,$h]) {
        $bcCrop = gd_crop($im, $x, $y, $w, $h);
        $bcAvg = gd_avg_rgb($bcCrop); imagedestroy($bcCrop);
        $bcHSV = rgb_to_hsv($bcAvg[0], $bcAvg[1], $bcAvg[2]);
        if ($bcHSV[1] <= 0.20 && $bcHSV[2] >= 0.28 && $bcHSV[2] <= 0.90) {
            imagedestroy($im);
            return 'common';
        }
    }

    // 2) General candidates (favor top regions, plus mid-top), smaller windows to reduce art influence
    $sizes = [0.08, 0.12, 0.16];
    $candidates = [];
    foreach ($sizes as $ratio) {
        $s = (int)max(10, round(min($iw, $ih) * $ratio));
        $padX = (int)round($iw * 0.06);
        $padYTop = (int)round($ih * 0.04);
        $padYMid = (int)round($ih * 0.10);
        // top-left / top-center / top-right
        $candidates[] = [max(0,$padX), max(0,$padYTop), min($s, $iw), min($s, $ih)]; // TL
        $candidates[] = [max(0,(int)round(($iw - $s)/2)), max(0,$padYTop), min($s,$iw), min($s,$ih)]; // TC
        $candidates[] = [max(0,$iw - $s - $padX), max(0,$padYTop), min($s, $iw), min($s, $ih)]; // TR
        // a bit lower on center (some layouts)
        $candidates[] = [max(0,(int)round(($iw - $s)/2)), max(0,$padYMid), min($s,$iw), min($s,$ih)]; // MC
    }
    // Also include the bottom-center probes as candidates for matching to non-grey rarities
    foreach ($bcCandidates as $bc) { $candidates[] = $bc; }

    $bestKey = '';
    $bestDist = 1e9;
    $secondBest = 1e9;
    $debugDistances = [];
    foreach ($candidates as [$x,$y,$w,$h]) {
        if ($w <= 0 || $h <= 0) continue;
        $crop = gd_crop($im, $x, $y, $w, $h);
        $avg = gd_avg_rgb($crop);
        imagedestroy($crop);
        $avgHSV = rgb_to_hsv($avg[0], $avg[1], $avg[2]);
        foreach ($iconAvgs as $key => $ref) {
            $d = hsv_distance($avgHSV, $iconHSV[$key]);
            if ($d < $bestDist) { $secondBest = $bestDist; $bestDist = $d; $bestKey = $key; }
            else if ($d < $secondBest) { $secondBest = $d; }
        }
    }
    imagedestroy($im);
    // Threshold to reject poor matches and require a margin vs second best
    $margin = $secondBest - $bestDist;
    if ($bestKey !== '' && $bestDist <= 0.28 && $margin >= 0.10) return $bestKey;
    return '';
}

function build_synonyms_map(array $keys, string $kind): array {
    // Provide some FR/EN synonym mappings to icon keys
    $map = [];
    if ($kind === 'rarity') {
        foreach ($keys as $k) { $map[$k] = $k; }
        $map['commun'] = $map['common'] ?? ($keys[0] ?? null);
        $map['peu-commune'] = $map['uncommon'] ?? null;
        $map['rare'] = $map['rare'] ?? ($map['epic'] ?? null);
        $map['epique'] = $map['epic'] ?? null;
        $map['légendaire'] = $map['legendaire'] = $map['legendary'] ?? null;
        // Alternatives special rarity synonyms
        if (isset($map['overnumbered'])) {
            $map['overnumber'] = $map['over-number'] = $map['overnumbered'];
        }
    } else if ($kind === 'color') {
        foreach ($keys as $k) { $map[$k] = $k; }
        $map['calme'] = $map['calm'] ?? null;
        $map['esprit'] = $map['mind'] ?? null;
        $map['corps'] = $map['body'] ?? null;
        $map['fureur'] = $map['fury'] ?? null;
        $map['ordre'] = $map['order'] ?? null;
        $map['incolore'] = $map['neutre'] = $map['aucune'] = $map['none'] = $map['neutral'] = $map['colorless'] ?? null;
        $map['chaos'] = $map['chaos'] ?? null;
    }
    // Remove nulls
    foreach ($map as $k => $v) { if ($v === null) unset($map[$k]); }
    return $map;
}

function normalize_to_known_enum(string $value, array $keys, array $synonyms): string {
    $v = rc_slug($value);
    if ($v === '') return '';
    if (in_array($v, $keys, true)) return $v;
    if (isset($synonyms[$v])) return (string)$synonyms[$v];
    // loose contains matching
    foreach ($keys as $k) {
        if ($v === $k) return $k;
        if ($v === rc_slug($k)) return $k;
        if (strpos($v, $k) !== false || strpos($k, $v) !== false) return $k;
    }
    return '';
}

function save_card_image(string $url, string $set, int $num, bool $force = false, string $variantSuffix = ''): array {
    $num4 = str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    $baseDir = __DIR__ . '/../assets/img/cards/' . $set;
    ensure_dir($baseDir);
    $data = null; $ct = null; $ext = 'jpg';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (RiftCollect Image Fetcher)'
        ]);
        $data = curl_exec($ch);
        $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($data) || $data === '') return ['error',$ext,''];
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 30,
            'header' => "User-Agent: Mozilla/5.0 (RiftCollect Image Fetcher)\r\nAccept: image/*\r\n",
        ]]);
        $data = @file_get_contents($url, false, $ctx);
        if (!is_string($data) || $data === '') return ['error',$ext,''];
    }
    $ext = map_ext_from_content_type($ct, $url);
    $suf = ($variantSuffix && preg_match('/^[a-z]$/i', $variantSuffix)) ? strtolower($variantSuffix) : '';
    $dest = $baseDir . '/card-' . $num4 . $suf . '.' . $ext;
    if (is_file($dest) && !$force) return ['exists',$ext,$dest];
    $ok = @file_put_contents($dest, $data);
    if ($ok === false) return ['error',$ext,$dest];
    return ['saved',$ext,$dest];
}

function ai_extract_from_image_url(string $imageUrl, string $id, string $set, string $model, string $apiKey, string $baseUrl, array $allowedColors = [], array $allowedRarities = [], int $timeoutSec = 60): ?array {
    if ($apiKey === '') return null;
    $system = 'You extract structured metadata from Riftbound TCG card images. '
            . 'Return only compact JSON matching the exact schema, no extra text.';
    $instructions = [
        'goal' => 'Extract card details from the provided image URL.',
        'schema' => [
            'card' => [
                'title' => 'string',
                'set' => 'string',
                'card_number' => 'string',
                'might' => 'number',
                'cost' => 'number',
                'card_type' => 'string',
                'color' => 'string',
                'rarity' => 'string',
                'effect' => 'string',
                'flavor_text' => 'string',
                'region' => ['array of strings'],
                'artist' => 'string',
                'year' => 'number',
                'collectible_info' => [
                    'set_size' => 'string',
                    'card_number_in_set' => 'string',
                    'publisher' => 'string'
                ]
            ]
        ],
        'rules' => [
            'Only output JSON object. No markdown.',
            'If unknown, put empty string or empty array; do not hallucinate.',
            'Prefer values printed on the card image itself.',
            'For color, only use one of: ' . implode(', ', $allowedColors),
            'For rarity, only use one of: ' . implode(', ', $allowedRarities)
        ],
        'id' => $id,
        'set' => $set,
        'image' => $imageUrl,
    ];

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => json_encode($instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]]
            ]],
        ],
        'temperature' => 0.1,
        'response_format' => ['type' => 'json_object']
    ];

    $url = rtrim($baseUrl, '/') . '/chat/completions';
    $res = null; $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSec),
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else if (ini_get('allow_url_fopen')) {
        // Fallback without cURL using streams
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $apiKey . "\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => $timeoutSec,
            ]
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        // Try to extract HTTP code from headers when available
        $meta = isset($http_response_header) ? $http_response_header : [];
        if (is_array($meta)) {
            foreach ($meta as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; break; }
            }
        }
    } else {
        return null; // cannot perform HTTP request
    }
    if ($code < 200 || $code >= 300 || !is_string($res) || $res === '') return null;
    $jr = json_decode($res, true);
    $content = $jr['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || $content === '') return null;
    $jsonText = $content;
    if ($jsonText[0] !== '{') {
        if (preg_match('/\{[\s\S]*\}/', $jsonText, $m)) $jsonText = $m[0];
    }
    $out = json_decode($jsonText, true);
    return is_array($out) ? $out : null;
}

function map_ai_json_to_db_fields(string $id, string $set, ?array $ai): array {
    // Normalize AI payload and add language layer with FR translation
    $card = is_array($ai) ? ($ai['card'] ?? $ai) : [];
    if (!is_array($card)) $card = [];
    $en = $card;
    $fr = $en;
    // Translate key textual fields using Database::translateText (cached)
    if (isset($fr['title'])) $fr['title'] = Database::translateText((string)$fr['title'], 'fr-FR');
    if (isset($fr['effect'])) $fr['effect'] = Database::translateText((string)$fr['effect'], 'fr-FR');
    if (isset($fr['flavor_text'])) $fr['flavor_text'] = Database::translateText((string)$fr['flavor_text'], 'fr-FR');
    // Build new payload with lang layer
    $payload = ['lang' => ['en' => $en, 'fr' => $fr]];

    // Pick FR for display columns when available, fallback to EN
    $name       = (string)($fr['title'] ?? $en['title'] ?? '');
    $rarity     = (string)($en['rarity'] ?? '');
    $color      = (string)($en['color'] ?? '');
    $cardType   = (string)($en['card_type'] ?? '');
    $effect     = (string)($fr['effect'] ?? $en['effect'] ?? '');
    // Normalize rarity/color to available icon keys if possible
    static $KNOWN_COLOR_KEYS = null, $KNOWN_RARITY_KEYS = null, $COLOR_SYNONYMS = null, $RARITY_SYNONYMS = null;
    if ($KNOWN_COLOR_KEYS === null) {
        $KNOWN_COLOR_KEYS = list_icon_keys('color');
        $KNOWN_RARITY_KEYS = list_icon_keys('rarity');
        $COLOR_SYNONYMS = build_synonyms_map($KNOWN_COLOR_KEYS, 'color');
        $RARITY_SYNONYMS = build_synonyms_map($KNOWN_RARITY_KEYS, 'rarity');
    }
    if ($color !== '') {
        $norm = normalize_to_known_enum($color, $KNOWN_COLOR_KEYS, $COLOR_SYNONYMS);
        if ($norm !== '') $color = $norm;
    }
    if ($rarity !== '') {
        $norm = normalize_to_known_enum($rarity, $KNOWN_RARITY_KEYS, $RARITY_SYNONYMS);
        if ($norm !== '') $rarity = $norm;
    }
    if ($name === '') $name = $id; // satisfy NOT NULL
    return [$name, $rarity, $color, $cardType, $effect, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
}

function has_existing_ai(?string $json): bool {
    if (!is_string($json) || trim($json) === '' || trim($json) === '[]' || trim($json) === '{}') return false;
    $arr = json_decode($json, true);
    if (!is_array($arr)) return false;
    // Support both old {card: {...}} and new {lang: {en:{...}, fr:{...}}}
    $card = null;
    if (isset($arr['card']) && is_array($arr['card'])) {
        $card = $arr['card'];
    } elseif (isset($arr['lang']['en']) && is_array($arr['lang']['en'])) {
        $card = $arr['lang']['en'];
    } elseif (isset($arr['lang']) && is_array($arr['lang'])) {
        // pick any first
        foreach ($arr['lang'] as $lang => $payload) { if (is_array($payload)) { $card = $payload; break; } }
    } else {
        $card = $arr;
    }
    if (!is_array($card)) return false;
    // consider having at least a title or any non-empty keys as "has AI"
    if (!empty($card['title'])) return true;
    foreach ($card as $k => $v) {
        if ($v !== null && $v !== '' && !(is_array($v) && count($v) === 0)) return true;
    }
    return false;
}

$setList = array_values(array_filter(array_map('trim', explode(',', $SETS))));
$numbers = parse_range($RANGE);

$ins = $pdo->prepare('REPLACE INTO ' . Database::t('cards_cache') . ' (id,name,rarity,set_code,image_url,color,card_type,description,data_json,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
$selExisting = $pdo->prepare('SELECT data_json FROM ' . Database::t('cards_cache') . ' WHERE id = ?');

$found = 0; $tried = 0; $aiUsed = 0; $start = time();
$results = [];
$processedFound = 0;
$deadlineAt = $MAX_SECONDS > 0 ? (microtime(true) + $MAX_SECONDS) : 0.0;

if ($OUT === 'text') {
    rc_out("RiftCollect CDN scan with Vision AI\n", $PROGRESS_FILE);
    rc_out("Base: $BASE\nSets: ".implode(',', $setList)."\nRange: $RANGE (".count($numbers)." candidates)\nAsset: $EXT\nDelay: {$DELAY}ms\nLLM: ".($LLM_ENABLED? ('enabled ('.$LLM_MODEL.', max='.$LLM_MAX_CALLS.')') : 'disabled')."\nImages dir: assets/img/cards/[SET]\n", $PROGRESS_FILE);
    // New: show runtime caps
    rc_out("MaxSec: ".($MAX_SECONDS ?: 'unlimited').", Limit(found): ".($MAX_ITEMS ?: 'unlimited').", Async: ".($ASYNC ? 'on' : 'off')."\n\n", $PROGRESS_FILE);
}

// Optional async mode: immediately return response and keep working in background
if ($ASYNC && PHP_SAPI !== 'cli') {
    // Ensure PHP keeps running even if client disconnects
    if (function_exists('ignore_user_abort')) { @ignore_user_abort(true); }
    if ($OUT === 'json') {
        echo json_encode([
            'status' => 'started',
            'pid' => getmypid(),
            'params' => [
                'sets' => $setList, 'range' => $RANGE, 'asset' => $EXT,
                'max_seconds' => $MAX_SECONDS, 'limit_found' => $MAX_ITEMS, 'llm' => (bool)$LLM_ENABLED
            ]
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } else {
        echo "[ASYNC] Background worker started (pid ".getmypid().")\n"; rc_flush();
    }
    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    if (function_exists('session_write_close')) { @session_write_close(); }
}

try {
    $abort = false;
    foreach ($setList as $set) {
        foreach ($numbers as $n) {
            // Check time/item caps before doing work
            if ($deadlineAt && microtime(true) >= $deadlineAt) { $abort = true; break; }
            if ($MAX_ITEMS > 0 && $processedFound >= $MAX_ITEMS) { $abort = true; break; }

            $tried++;
            $num = str_pad((string)$n, 3, '0', STR_PAD_LEFT);
            $suffixes = $ALT_ONLY ? ['a','s'] : [''];
            foreach ($suffixes as $suffix) {
                // Check caps again within suffix loop
                if ($deadlineAt && microtime(true) >= $deadlineAt) { $abort = true; break; }
                if ($MAX_ITEMS > 0 && $processedFound >= $MAX_ITEMS) { $abort = true; break; }

                $id = $set . '-' . $num . $suffix;
                $cardBase = rtrim($BASE, '/') . '/' . rawurlencode($set) . '/cards/' . rawurlencode($id);
                $url = $cardBase . '/' . rawurlencode($EXT);

                $code = head_status($url);
                if ($code >= 200 && $code < 300) {
                    $found++;
                    $processedFound++;
                    if ($OUT === 'text') { rc_out("F $id -> $url\n", $PROGRESS_FILE); }

                    // Save image locally
                    [$st, $imgExt, $path] = save_card_image($url, $set, (int)$n, (bool)$IMG_FORCE, $suffix);
                    if ($OUT === 'text') {
                        if ($st === 'saved') { rc_out("  IMG: saved ($imgExt)\n", $PROGRESS_FILE); }
                        elseif ($st === 'exists') { rc_out("  IMG: exists\n", $PROGRESS_FILE); }
                        else { rc_out("  IMG: error\n", $PROGRESS_FILE); }
                    }

                    // AI extraction
                    $aiJson = null;
                    static $calls = 0;
                    if ($LLM_ENABLED && $OPENAI_API_KEY !== '' && $calls < $LLM_MAX_CALLS) {
                        // Decide whether to re-run AI based on existing DB entry
                        $shouldRun = true;
                        if ($LLM_RETRY_ONLY && !$LLM_OVERWRITE) {
                            $selExisting->execute([$id]);
                            $row = $selExisting->fetch(PDO::FETCH_ASSOC);
                            $has = $row ? has_existing_ai((string)$row['data_json']) : false;
                            if ($has) $shouldRun = false;
                        }
                        if ($shouldRun || $LLM_OVERWRITE) {
                            if ($OUT === 'text') { rc_out("  AI: call... ", $PROGRESS_FILE); }
                            // Provide allowed enums to the model to stabilize outputs
                            $allowedColors = list_icon_keys('color');
                            $allowedRarities = list_icon_keys('rarity');
                            $aiJson = ai_extract_from_image_url($url, $id, $set, $LLM_MODEL, $OPENAI_API_KEY, $LLM_BASE_URL, $allowedColors, $allowedRarities, $LLM_TIMEOUT);
                            $calls++;
                            if (is_array($aiJson)) { $aiUsed++; if ($OUT === 'text') { rc_out("ok\n", $PROGRESS_FILE); } }
                            else { if ($OUT === 'text') { rc_out("failed\n", $PROGRESS_FILE); } }
                        } else {
                            if ($OUT === 'text') { rc_out("  AI: skipped (already present)\n", $PROGRESS_FILE); }
                        }
                    } else {
                        if ($OUT === 'text') { rc_out("  AI: skipped (disabled or quota)\n", $PROGRESS_FILE); }
                    }

                    // If rarity is empty or AI skipped/failed, try image-based detection using local rarity icons
                    try {
                        $rarityDetected = '';
                        if (isset($path) && is_string($path) && $path !== '') {
                            $iconsMap = list_icon_map('rarity');
                            if ($iconsMap) {
                                $rarityDetected = detect_rarity_from_image($path, $iconsMap);
                            }
                        }
                        if ($rarityDetected !== '') {
                            if (!is_array($aiJson)) $aiJson = ['card' => []];
                            if (!isset($aiJson['card']) || !is_array($aiJson['card'])) $aiJson['card'] = [];
                            $cur = (string)($aiJson['card']['rarity'] ?? '');
                            // Prefer visual detection over LLM text when they disagree
                            if ($cur !== '' && strtolower($cur) !== strtolower($rarityDetected)) {
                                $aiJson['card']['rarity_ai'] = $cur; // keep original for traceability
                            }
                            $aiJson['card']['rarity'] = $rarityDetected;
                            if ($OUT === 'text') { rc_out("  RARITY: detected -> $rarityDetected\n", $PROGRESS_FILE); }
                        } else {
                            if ($OUT === 'text') { rc_out("  RARITY: not detected\n", $PROGRESS_FILE); }
                        }
                    } catch (Throwable $e) {
                        if ($OUT === 'text') { rc_out("  RARITY: detection error\n", $PROGRESS_FILE); }
                    }

                    // Variant cards (e.g., OGN-007a) are always 'overnumbered'
                    $isVariant = (bool)preg_match('/-[0-9]{1,4}[a-z]$/i', $id);
                    if ($isVariant) {
                        if (!is_array($aiJson)) $aiJson = ['card' => []];
                        if (!isset($aiJson['card']) || !is_array($aiJson['card'])) $aiJson['card'] = [];
                        $aiJson['card']['rarity'] = 'overnumbered';
                        if ($OUT === 'text') { rc_out("  RARITY: variant -> overnumbered\n", $PROGRESS_FILE); }
                    }

                    // Map and upsert DB
                    [$name, $rarity, $color, $cardType, $effect, $dataJson] = map_ai_json_to_db_fields($id, $set, $aiJson ?: []);
                    $now = time();
                    $ins->execute([$id, $name, $rarity, $set, $url, $color, $cardType, $effect, $dataJson, $now]);

                    // Optional: print or save AI JSON
                    if (is_array($aiJson)) {
                        if ($SAVE_AI) {
                            $dir = __DIR__ . '/../storage/ai_json'; ensure_dir($dir);
                            @file_put_contents($dir . '/' . $id . '.json', json_encode($aiJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                        }
                        if ($OUT === 'text' && $PRINT_AI) {
                            rc_out(json_encode($aiJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", $PROGRESS_FILE);
                        }
                    }

                    // Aggregate for JSON response
                    if ($OUT === 'json') {
                        $results[] = [
                            'id' => $id,
                            'set' => $set,
                            'image_url' => $url,
                            'ai' => $aiJson,
                            'saved_image' => isset($path) ? $path : null,
                            'updated_at' => $now,
                        ];
                    }
                } else {
                    if ($OUT === 'text') {
                        if ($code === 403) rc_out("D", $PROGRESS_FILE);
                        elseif ($code === 404 || $code === 0) rc_out(".", $PROGRESS_FILE);
                        else rc_out("(".$code.")", $PROGRESS_FILE);
                    } else {
                        // For JSON mode, we skip logging individual misses to keep payload small
                    }
                }

                @set_time_limit(30);
                if ($DELAY > 0) usleep($DELAY * 1000);
            }
        }
        if ($OUT === 'text') { rc_out("\n", $PROGRESS_FILE); }
        if ($abort) break;
    }
} catch (Throwable $e) {
    if ($OUT === 'text') rc_out("\nRUNTIME ERROR: " . $e->getMessage() . "\n", $PROGRESS_FILE);
    error_log('[scan_cdn_cards] RUNTIME ERROR: ' . $e->getMessage());
    http_response_code(500);
    if ($OUT === 'json') {
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    exit;
}

$dur = time() - $start;
if ($OUT === 'text') {
    rc_out("\nDone. Tried: $tried, Found: $found, AI used: $aiUsed, Duration: {$dur}s\n", $PROGRESS_FILE);
} else {
    echo json_encode([
        'summary' => [
            'tried' => $tried,
            'found' => $found,
            'ai_used' => $aiUsed,
            'duration_seconds' => $dur,
            'base' => $BASE,
            'sets' => $setList,
            'range' => $RANGE,
            'asset' => $EXT,
        ],
        'items' => $results,
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
