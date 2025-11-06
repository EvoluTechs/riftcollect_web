<?php
// RiftCollect - Migrate existing AI JSON to language-aware format and save French translation
// Usage (browser or CLI):
//   /cron/migrate_ai_lang.php?limit=200&force=0&out=text
// Params:
//   - limit: max rows to process (default 200; 0 = all)
//   - force: 1 to rebuild lang payload even if already present (default 0)
//   - out:   text|json (default text)

declare(strict_types=1);

@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../storage/logs/migrate_ai_lang.log');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../inc/Config.php';
require_once __DIR__ . '/../inc/Database.php';

use RiftCollect\Config;
use RiftCollect\Database;

Config::init();
$db = Database::pdo();

$limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 200; // 0 = all
$force = !empty($_GET['force']) ? 1 : 0;
$out   = strtolower(trim((string)($_GET['out'] ?? 'text')));
if ($out !== 'json') $out = 'text';
if ($out === 'json') header('Content-Type: application/json; charset=utf-8');

function logln(string $s): void { if (php_sapi_name() === 'cli') { echo $s, "\n"; } else { echo $s, "\n"; flush(); } }

$sel = $db->prepare('SELECT id, set_code, name, description, data_json, updated_at FROM ' . \RiftCollect\Database::t('cards_cache') . ' ORDER BY updated_at DESC');
$sel->execute();
$rows = $sel->fetchAll();
$processed = 0; $migrated = 0; $skipped = 0; $errors = 0;
$list = [];

foreach ($rows as $r) {
    if ($limit > 0 && $processed >= $limit) break;
    $processed++;
    $id = (string)$r['id'];
    $json = (string)($r['data_json'] ?? '');
    if (trim($json) === '' || $json === '[]' || $json === '{}') { $skipped++; continue; }
    $src = json_decode($json, true);
    if (!is_array($src)) { $skipped++; continue; }
    if (!$force && isset($src['lang']) && is_array($src['lang'])) { $skipped++; continue; }

    // Determine EN card object
    $card = $src['card'] ?? $src;
    if (!is_array($card)) $card = [];
    $en = $card;
    // Build FR by translating a few fields
    $fr = $en;
    if (isset($fr['title'])) $fr['title'] = Database::translateText((string)$fr['title'], 'fr-FR');
    if (isset($fr['effect'])) $fr['effect'] = Database::translateText((string)$fr['effect'], 'fr-FR');
    if (isset($fr['flavor_text'])) $fr['flavor_text'] = Database::translateText((string)$fr['flavor_text'], 'fr-FR');

    $payload = [ 'lang' => [ 'en' => $en, 'fr' => $fr ] ];
    $nameFr = (string)($fr['title'] ?? $en['title'] ?? $r['name'] ?? $id);
    $descFr = (string)($fr['effect'] ?? $en['effect'] ?? $r['description'] ?? '');

    try {
    $upd = $db->prepare('UPDATE ' . \RiftCollect\Database::t('cards_cache') . ' SET name = ?, description = ?, data_json = ?, updated_at = ? WHERE id = ?');
        $upd->execute([$nameFr, $descFr, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), time(), $id]);
        $migrated++;
        if ($out === 'text') logln("OK $id");
        else $list[] = ['id' => $id, 'migrated' => true];
    } catch (\Throwable $e) {
        $errors++;
        if ($out === 'text') logln("ERR $id: " . $e->getMessage());
        else $list[] = ['id' => $id, 'error' => $e->getMessage()];
    }
}

if ($out === 'text') {
    logln("\nDone. Processed: $processed, Migrated: $migrated, Skipped: $skipped, Errors: $errors");
} else {
    echo json_encode([
        'summary' => compact('processed','migrated','skipped','errors'),
        'items' => $list,
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
