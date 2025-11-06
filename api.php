<?php
// RiftCollect API endpoint (single entry)
// Routes with action parameter, returns JSON
// Supports: auth (register/login/logout/me), cards (list/detail), collection (CRUD), stats, expansions/news

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/inc/Config.php';
require_once __DIR__ . '/inc/Database.php';
require_once __DIR__ . '/inc/Auth.php';

use RiftCollect\Config;
use RiftCollect\Database;
use RiftCollect\Auth;

// Initialize app services
Config::init();
$db = Database::instance();
Auth::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json_ok($data = [], int $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}
function json_error(string $message, int $code = 400, $extra = null) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message, 'extra' => $extra]);
    exit;
}

try {
    switch ($action) {
        case 'health':
            json_ok(['status' => 'alive', 'time' => time()]);
        case 'db.health':
            // Simple R/W healthcheck against the database (uses translations table and cleans up)
            try {
                $pdo = Database::instance();
                // Ensure connection and get server version if available
                $ver = null;
                try {
                    $ver = $pdo->query('SELECT VERSION() AS v')->fetch()['v'] ?? null;
                } catch (Throwable $e) {
                    $ver = null;
                }
                $tbl = Database::t('translations');
                $hash = 'health-' . bin2hex(random_bytes(8));
                $now = time();
                // Write
                $stmt = $pdo->prepare('REPLACE INTO ' . $tbl . ' (src_lang, dst_lang, src_hash, src_text, dst_text, updated_at) VALUES (?,?,?,?,?,?)');
                $stmt->execute([null, 'fr-FR', $hash, 'ping', 'pong', $now]);
                // Read
                $sel = $pdo->prepare('SELECT dst_text, updated_at FROM ' . $tbl . ' WHERE src_hash = ?');
                $sel->execute([$hash]);
                $row = $sel->fetch();
                // Clean
                $del = $pdo->prepare('DELETE FROM ' . $tbl . ' WHERE src_hash = ?');
                $del->execute([$hash]);
                if (!$row || ($row['dst_text'] ?? '') !== 'pong') {
                    json_error('DB readback mismatch', 500);
                }
                json_ok(['db' => 'ok', 'driver' => \RiftCollect\Config::$DB_DRIVER, 'mysql_version' => $ver, 'time' => $now]);
            } catch (Throwable $e) {
                json_error('DB health failed', 500, ['message' => $e->getMessage()]);
            }
        case 'cron.progress':
            // Tail-like reader for scan progress files in storage/logs
            $file = basename((string)($_GET['file'] ?? ''));
            if ($file === '' || !preg_match('/^scan-[a-zA-Z0-9._-]+\.log$/', $file)) {
                json_error('Fichier invalide', 400);
            }
            $pos = max(0, (int)($_GET['pos'] ?? 0));
            $path = __DIR__ . '/storage/logs/' . $file;
            if (!is_file($path)) {
                json_ok(['chunk' => '', 'size' => 0, 'pos' => 0]);
            }
            $size = filesize($path) ?: 0;
            if ($pos > $size) { $pos = max(0, $size - 8192); }
            $fh = fopen($path, 'rb');
            if ($fh === false) json_error('Impossible d\'ouvrir le fichier', 500);
            if ($pos > 0) fseek($fh, $pos);
            $data = stream_get_contents($fh);
            $newPos = ftell($fh);
            fclose($fh);
            json_ok(['chunk' => $data ?: '', 'size' => $size, 'pos' => (int)$newPos]);
        case 'landing.images':
            // List images from assets/img/riftbound (non-recursive); if empty, fallback to public CDN images from Config
            $dir = __DIR__ . '/assets/img/riftbound';
            $urls = [];
            if (is_dir($dir)) {
                $files = scandir($dir) ?: [];
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
                    // expose as relative URL
                    $urls[] = 'assets/img/riftbound/' . rawurlencode($f);
                }
            }
            // If none locally, use configured CDN images
            if (count($urls) === 0 && !empty(Config::$CDN_IMAGES)) {
                $urls = Config::$CDN_IMAGES;
            }
            // sort relative URLs only (CDN order preserved)
            $rel = array_filter($urls, fn($u) => !preg_match('#^https?://#i', $u));
            if ($rel) { natsort($rel); }
            // merge sorted relative first, then CDN
            $cdn = array_values(array_filter($urls, fn($u) => preg_match('#^https?://#i', $u)));
            $urls = array_values(array_merge($rel ? array_values($rel) : [], $cdn));
            json_ok(['images' => $urls]);

        case 'rarity.icons':
            // List rarity icons from assets/img/rarity (non-recursive)
            $dir = __DIR__ . '/assets/img/rarity';
            $items = [];
            if (is_dir($dir)) {
                $files = scandir($dir) ?: [];
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) continue;
                    $key = strtolower(pathinfo($f, PATHINFO_FILENAME));
                    $items[] = [
                        'key' => $key,
                        'url' => 'assets/img/rarity/' . rawurlencode($f),
                        'ext' => $ext,
                    ];
                }
            }
            // Deduplicate by key (prefer first encountered)
            $seen = [];
            $out = [];
            foreach ($items as $it) {
                if (!isset($seen[$it['key']])) { $seen[$it['key']] = true; $out[] = $it; }
            }
            // Sort by a common order if present
            $order = ['common','uncommon','rare','epic','legendary'];
            usort($out, function($a,$b) use ($order){
                $ia = array_search($a['key'], $order, true); if ($ia === false) $ia = 999;
                $ib = array_search($b['key'], $order, true); if ($ib === false) $ib = 999;
                if ($ia === $ib) return strcmp($a['key'], $b['key']);
                return $ia <=> $ib;
            });
            json_ok(['icons' => $out]);

        case 'register':
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Email invalide');
            if (strlen($password) < 8) json_error('Mot de passe trop court (min 8)');
            $uid = Auth::register($email, $password);
            json_ok(['user_id' => $uid]);

        case 'login':
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $user = Auth::login($email, $password);
            if (!$user) json_error('Identifiants invalides', 401);
            json_ok(['user' => ['id' => $user['id'], 'email' => $user['email'], 'is_admin' => Auth::isAdmin()]]);

        case 'logout':
            Auth::logout();
            json_ok(['bye' => true]);

        case 'me':
            $u = Auth::user();
            if (!$u) json_error('Non connecté', 401);
            json_ok(['user' => ['id' => $u['id'], 'email' => $u['email'], 'is_admin' => Auth::isAdmin()]]);

        case 'cards.list':
            // Filters: q (search), rarity, set, page, pageSize
            $q = trim($_GET['q'] ?? '');
            $rarity = trim($_GET['rarity'] ?? '');
            $set = trim($_GET['set'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 30)));
            $color = trim($_GET['color'] ?? '');
            $ctype = trim($_GET['type'] ?? '');
            $data = Database::cardsList($q, $rarity, $set, $page, $pageSize, $color, $ctype);
            json_ok($data);

        case 'cards.refresh':
            // Admin only
            try { Auth::requireAdmin(); } catch (\Throwable $e) { json_error('Accès administrateur requis', 403); }
            $count = Database::syncCardsFromApi();
            json_ok(['synced' => $count]);

        case 'cards.detail':
            $id = trim($_GET['id'] ?? '');
            $locale = trim($_GET['locale'] ?? 'fr-FR');
            if ($id === '') json_error('Paramètre id manquant');
            $card = Database::cardDetail($id, $locale);
            if (!$card) json_error('Carte introuvable', 404);
            json_ok($card);

        case 'cards.matchImage':
            // Compare a provided image (data URL or URL) against local card thumbnails using dHash
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            if (!function_exists('imagecreatetruecolor')) json_error('GD non disponible sur le serveur', 500);
            // Load hashing utility only for this endpoint (avoid breaking other routes if the file is missing)
            $ihPath = __DIR__ . '/inc/ImageHash.php';
            if (!is_file($ihPath)) json_error('Module ImageHash manquant sur le serveur', 500);
            require_once $ihPath;
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) json_error('JSON invalide');
            $dataUrl = (string)($body['image'] ?? '');
            $limit = max(1, min(10, (int)($body['limit'] ?? 5)));
            $setFilter = trim((string)($body['set'] ?? ''));
            if ($dataUrl === '') json_error('image manquante');
            // Decode data URL
            $imgData = null;
            if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $dataUrl, $m)) {
                $imgData = base64_decode($m[1]);
            } elseif (preg_match('#^https?://#i', $dataUrl)) {
                $imgData = @file_get_contents($dataUrl);
            }
            if (!is_string($imgData) || $imgData === '') json_error('Image invalide');
            $im = @imagecreatefromstring($imgData);
            if (!$im) json_error('Image illisible');
            // Normalize orientation-ish by simple max-dimension downscale (speed)
            $w = imagesx($im); $h = imagesy($im);
            $maxSide = 512; $tw = $w; $th = $h;
            if (max($w,$h) > $maxSide) { $scale = $maxSide / max($w,$h); $tw=(int)max(1,round($w*$scale)); $th=(int)max(1,round($h*$scale)); }
            if ($tw !== $w || $th !== $h) { $tmp = imagecreatetruecolor($tw,$th); imagecopyresampled($tmp,$im,0,0,0,0,$tw,$th,$w,$h); imagedestroy($im); $im=$tmp; }
            $queryHash = \RiftCollect\ImageHash::dhashFromGd($im, 8);
            imagedestroy($im);
            if (!$queryHash) json_error('Hash impossible');

            // Build or load cache
            $assetsRoot = __DIR__ . '/assets/img';
            $cachePath = __DIR__ . '/storage/hashes/cards_dhash.json';
            $map = \RiftCollect\ImageHash::buildHashCache($db, $assetsRoot, $cachePath);
            if (!$map) json_ok(['matches' => []]);
            // Optional filtering by set
            $ids = array_keys($map);
            if ($setFilter !== '') {
                $ids = array_values(array_filter($ids, function($id) use ($setFilter){ return stripos($id, strtoupper($setFilter) . '-') === 0; }));
            }
            // Compute distances
            $scores = [];
            foreach ($ids as $id) {
                $hhex = (string)($map[$id]['hash'] ?? ''); if ($hhex === '') continue;
                $d = \RiftCollect\ImageHash::hammingDistHex($queryHash, $hhex);
                $scores[] = [$id, $d];
            }
            if (!$scores) json_ok(['matches' => []]);
            usort($scores, function($a,$b){ return $a[1] <=> $b[1]; });
            $top = array_slice($scores, 0, $limit);
            $idsTop = array_map(fn($x) => $x[0], $top);
            // Fetch basic info
            $placeholders = implode(',', array_fill(0, count($idsTop), '?'));
            $stmt = $db->prepare('SELECT id,name,set_code,image_url FROM ' . Database::t('cards_cache') . ' WHERE id IN (' . $placeholders . ')');
            $stmt->execute($idsTop);
            $info = [];
            while ($row = $stmt->fetch()) { $info[$row['id']] = $row; }
            $matches = [];
            foreach ($top as [$id,$dist]) {
                $row = $info[$id] ?? ['id'=>$id, 'name'=>null, 'image_url'=>null, 'set_code'=>null];
                $matches[] = [
                    'id' => $id,
                    'name' => $row['name'],
                    'set' => $row['set_code'],
                    'distance' => $dist,
                    'image_url' => $row['image_url'],
                ];
            }
            json_ok(['query_hash' => $queryHash, 'matches' => $matches]);

        case 'cards.matchImage.health':
            // Diagnostics for image matching environment
            $gd = function_exists('imagecreatetruecolor');
            $ihPath = __DIR__ . '/inc/ImageHash.php';
            $hasIH = is_file($ihPath);
            $cachePath = __DIR__ . '/storage/hashes/cards_dhash.json';
            $cacheOk = is_file($cachePath);
            $cacheCount = 0;
            if ($cacheOk) {
                $txt = @file_get_contents($cachePath);
                $arr = json_decode((string)$txt, true);
                if (is_array($arr)) $cacheCount = count($arr);
            }
            $cardsCount = 0;
            try {
                $row = $db->query('SELECT COUNT(*) AS n FROM ' . Database::t('cards_cache'))->fetch();
                $cardsCount = (int)($row['n'] ?? 0);
            } catch (\Throwable $e) {}
            $assetsDir = __DIR__ . '/assets/img/cards';
            $assetsOk = is_dir($assetsDir);
            json_ok([
                'gd' => $gd,
                'imagehash_file' => $hasIH,
                'assets_cards_dir' => $assetsOk,
                'cards_in_db' => $cardsCount,
                'hash_cache_file' => $cacheOk,
                'hash_cache_count' => $cacheCount,
            ]);

        case 'cards.matchAi':
            // Vision LLM: detect card ID like OGN-310 from an image
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            if (!\RiftCollect\Config::$LLM_ENABLED) json_error('LLM non configuré', 503);
            $apiKey = (string)(\RiftCollect\Config::$OPENAI_API_KEY ?? '');
            $baseUrl = (string)(\RiftCollect\Config::$LLM_BASE_URL ?? 'https://api.openai.com/v1');
            $model = (string)(\RiftCollect\Config::$LLM_MODEL ?? 'gpt-4o-mini');
            if ($apiKey === '') json_error('Clé API LLM manquante', 503);
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) json_error('JSON invalide');
            $dataUrl = (string)($body['image'] ?? '');
            $setFilter = trim((string)($body['set'] ?? ''));
            if ($dataUrl === '') json_error('image manquante');
            if (!preg_match('#^data:image/[^;]+;base64,#', $dataUrl)) json_error('Data URL image attendue');
            // Build prompt that forces a strict JSON answer
            $prompt = "Tu es un assistant de collection de cartes Riftbound.\n" .
                "Analyse l'image fournie et trouve l'identifiant imprimé de la carte au format SET-NUMERO (ex: OGN-310).\n" .
                ($setFilter !== '' ? ("Le set attendu commence par: " . strtoupper($setFilter) . "\n") : '') .
                "Réponds STRICTEMENT en JSON avec la forme: {\"ids\":[\"OGN-310\",\"...\"]}. Ne mets pas d'autre texte.";
            $payload = [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ]],
                'temperature' => 0.2,
                'max_tokens' => 100,
            ];
            // cURL request
            $ch = curl_init(rtrim($baseUrl, '/') . '/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
            ]);
            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                curl_close($ch);
                json_error('Appel LLM échoué', 502, ['message' => $err]);
            }
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $j = json_decode((string)$resp, true);
            if ($http < 200 || $http >= 300 || !is_array($j)) {
                json_error('Réponse LLM invalide', 502, ['http' => $http, 'body' => substr((string)$resp, 0, 500)]);
            }
            // Extract message content
            $text = '';
            if (isset($j['choices'][0]['message']['content'])) {
                $text = (string)$j['choices'][0]['message']['content'];
            } elseif (isset($j['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
                $text = (string)$j['choices'][0]['message']['tool_calls'][0]['function']['arguments'];
            }
            $ids = [];
            // Prefer strict JSON
            $jsonBlock = null;
            if ($text !== '') {
                $maybe = json_decode($text, true);
                if (is_array($maybe) && isset($maybe['ids']) && is_array($maybe['ids'])) {
                    $jsonBlock = $maybe;
                }
            }
            if ($jsonBlock) {
                foreach ($jsonBlock['ids'] as $id) {
                    if (is_string($id) && preg_match('/^[A-Z]{2,5}-\d{1,4}$/', strtoupper($id))) {
                        $ids[] = strtoupper($id);
                    }
                }
            }
            // Fallback: regex in free text
            if (!$ids && $text) {
                if (preg_match_all('/\b([A-Z]{2,5})\s*[-–—]\s*(\d{1,4})\b/u', strtoupper($text), $m)) {
                    for ($i=0; $i<count($m[0]); $i++) $ids[] = $m[1][$i] . '-' . $m[2][$i];
                }
            }
            $ids = array_values(array_unique($ids));
            // Validate against DB
            $valid = [];
            foreach ($ids as $id) {
                try {
                    $row = Database::cardDetail($id, 'fr-FR');
                    if ($row) $valid[] = $row;
                } catch (\Throwable $e) {}
                if (count($valid) >= 5) break;
            }
            json_ok(['candidates' => array_map(function($r){
                return [
                    'id' => $r['id'] ?? null,
                    'name' => $r['name'] ?? null,
                    'set' => $r['set_code'] ?? null,
                    'image_url' => $r['image_url'] ?? null,
                ];
            }, $valid)]);

        case 'config.flags':
            // Expose non-sensibles runtime feature flags (no secrets)
            $gd = function_exists('imagecreatetruecolor');
            $ihPath = __DIR__ . '/inc/ImageHash.php';
            $hasIH = is_file($ihPath);
            $assetsDir = __DIR__ . '/assets/img/cards';
            $assetsOk = is_dir($assetsDir);
            json_ok([
                'llmEnabled' => (bool)\RiftCollect\Config::$LLM_ENABLED,
                'llmModel' => (string)\RiftCollect\Config::$LLM_MODEL,
                'imageMatchAvailable' => (bool)($gd && $hasIH && $assetsOk),
            ]);

        case 'collection.get':
            $u = Auth::requireUser();
            $items = Database::collectionGet($u['id']);
            json_ok(['items' => $items]);

        case 'collection.set':
            $u = Auth::requireUser();
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            $cardId = trim($_POST['card_id'] ?? '');
            $qty = max(0, (int)($_POST['qty'] ?? 0));
            if ($cardId === '') json_error('card_id requis');
            Database::collectionSet($u['id'], $cardId, $qty);
            json_ok(['card_id' => $cardId, 'qty' => $qty]);

        case 'collection.bulkSet':
            $u = Auth::requireUser();
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) json_error('JSON invalide');
            $count = Database::collectionBulkSet($u['id'], $payload);
            json_ok(['updated' => $count]);

        case 'stats.progress':
            $u = Auth::requireUser();
            $stats = Database::statsProgress($u['id']);
            json_ok($stats);

        case 'expansions.list':
            $list = Database::expansionsList();
            json_ok(['expansions' => $list]);

        case 'card.price':
            // Lightweight pricing stub for live badge. Replace with a real provider when available.
            // Params: key (card id like OGN-030)
            $key = trim((string)($_GET['key'] ?? ''));
            if ($key === '') json_error('key manquant', 400);
            // Fetch minimal info
            $stmt = $db->prepare('SELECT id, rarity, data_json FROM ' . Database::t('cards_cache') . ' WHERE id = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if (!$row) json_error('Carte introuvable', 404);
            $rar = strtolower((string)($row['rarity'] ?? ''));
            // If rarity is embedded in JSON, try to extract
            if ($rar === '' && !empty($row['data_json'])) {
                $dj = strtolower((string)$row['data_json']);
                if (preg_match('/"rarity"\s*:\s*"([a-z]+)"/i', $dj, $m)) { $rar = strtolower($m[1]); }
            }
            // Basic mapping (EUR) — adjust when real pricing source is integrated
            $priceMap = [
                'common' => 0.10,
                'commune' => 0.10,
                'uncommon' => 0.25,
                'peu commune' => 0.25,
                'rare' => 1.00,
                'epic' => 3.50,
                'epique' => 3.50,
                'legendary' => 8.00,
                'legendaire' => 8.00,
                'overnumbered' => 15.00,
            ];
            $price = $priceMap[$rar] ?? 0.00;
            // Slight bump for variant letters (e.g., OGN-007a)
            if (preg_match('/-[0-9]{1,4}[a-z]$/i', $key)) { $price = max($price, $price * 1.15); }
            json_ok(['price' => round($price, 2), 'currency' => 'EUR']);

        case 'subscribe':
            $u = Auth::requireUser();
            if ($method !== 'POST') json_error('Méthode non autorisée', 405);
            $enabled = (int)($_POST['enabled'] ?? 1) ? 1 : 0;
            Database::subscriptionSet($u['id'], $enabled);
            json_ok(['notifications' => $enabled === 1]);

        default:
            json_error('Action inconnue', 404, ['action' => $action]);
    }
} catch (Throwable $e) {
    json_error('Erreur serveur', 500, [
        'message' => $e->getMessage(),
    ]);
}
