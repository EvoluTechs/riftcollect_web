<?php
// Migrate data from an existing SQLite file to the configured MySQL database for RiftCollect
// Usage examples:
// - Browser: /cron/migrate_sqlite_to_mysql.php?src=storage/riftcollect.sqlite&dry=0
// - CLI: php cron/migrate_sqlite_to_mysql.php src=storage/riftcollect.sqlite dry=0
// Params:
//   - src: Path to the SQLite file (default from RC_SQLITE_FILE or storage/riftcollect.sqlite)
//   - dry: 1 = do not write to MySQL (default 0)
//   - limit: max rows per table to migrate (0 = no limit)
//   - tables: comma-separated subset (e.g. users,cards_cache,collections,...)
// Notes:
// - Target MySQL connection comes from Config (RC_MYSQL_* env). Ensure Config::$DB_DRIVER is 'mysql'.
// - The script auto-creates target schema by initializing Database::instance().
// - For source (SQLite), tables may be prefixed or not; the script detects either.

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../storage/logs/migrate_sqlite_to_mysql.log');

require_once __DIR__ . '/../inc/Config.php';
require_once __DIR__ . '/../inc/Database.php';

use RiftCollect\Config;
use RiftCollect\Database;
use PDO; use Exception; use Throwable;

// Allow CLI style key=value args
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    for ($i=1; $i<count($argv); $i++) {
        if (strpos($argv[$i], '=') !== false) { [$k,$v] = explode('=', $argv[$i], 2); $_GET[$k] = $v; }
    }
}

function outln(string $s): void { echo $s, "\n"; flush(); }

Config::init();
if (Config::$DB_DRIVER !== 'mysql') {
    outln('[ERROR] Target DB driver is not mysql. Set RC_DB_DRIVER=mysql and retry.');
    exit(1);
}

$src = isset($_GET['src']) ? (string)$_GET['src'] : (getenv('RC_SQLITE_FILE') ?: (__DIR__ . '/../storage/riftcollect.sqlite'));
$src = str_replace('\\', '/', $src);
if (!preg_match('#^([a-zA-Z]:/|/)#', $src)) { $src = realpath(__DIR__ . '/../' . $src) ?: $src; }
$dry = !empty($_GET['dry']) ? 1 : 0;
$limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0;
$onlyTables = isset($_GET['tables']) ? array_values(array_filter(array_map('trim', explode(',', (string)$_GET['tables'])))) : [];

if (!is_file($src)) {
    outln('[ERROR] SQLite file not found: ' . $src);
    exit(1);
}

// Connect to source SQLite
$srcDsn = 'sqlite:' . $src;
try { $srcPdo = new PDO($srcDsn); $srcPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
catch (Throwable $e) { outln('[ERROR] Cannot open SQLite: ' . $e->getMessage()); exit(1); }
// Target MySQL (and ensure tables exist)
$destPdo = Database::instance();

$prefix = Config::$DB_TABLE_PREFIX;

// Helper: resolve source table name (prefixed or not)
function srcTable(PDO $src, string $base, string $prefix): ?string {
    $candidates = [$prefix . $base, $base];
    foreach ($candidates as $t) {
        try { $q = $src->query("SELECT 1 FROM " . $t . " LIMIT 1"); if ($q) return $t; } catch (Throwable $e) { /* try next */ }
    }
    return null;
}

// Helper: list columns for SQLite table
function sqliteColumns(PDO $src, string $table): array {
    $cols = [];
    try {
        $rs = $src->query("PRAGMA table_info(" . $table . ")");
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) { $cols[] = (string)$row['name']; }
    } catch (Throwable $e) {}
    return $cols;
}

// Helper: copy table with REPLACE into destination
function copyTable(PDO $src, PDO $dst, string $srcTable, string $dstTable, array $colOrder, int $limit = 0, bool $dry = false): array {
    $count = 0; $errors = 0;
    $colsSql = '`' . implode('`,`', array_map(fn($c)=>str_replace('`','',$c), $colOrder)) . '`';
    $placeholders = implode(',', array_fill(0, count($colOrder), '?'));
    $insSql = 'REPLACE INTO ' . $dstTable . ' (' . $colsSql . ') VALUES (' . $placeholders . ')';
    $ins = $dry ? null : $dst->prepare($insSql);
    $sql = 'SELECT ' . implode(',', array_map(fn($c)=>'"' . $c . '"', $colOrder)) . ' FROM ' . $srcTable;
    if ($limit > 0) { $sql .= ' LIMIT ' . (int)$limit; }
    $st = $src->query($sql);
    while ($row = $st->fetch(PDO::FETCH_NUM)) {
        $count++;
        if (!$dry) {
            try { $ins->execute($row); }
            catch (Throwable $e) { $errors++; outln('[WARN] ' . $dstTable . ' row error: ' . $e->getMessage()); }
        }
    }
    return ['copied' => $count, 'errors' => $errors];
}

$tables = [
    'users' => ['id','email','password_hash','created_at'],
    'cards_cache' => ['id','name','rarity','set_code','image_url','data_json','updated_at','color','card_type','description'],
    'collections' => ['user_id','card_id','qty'],
    'expansions' => ['code','name','released_at','data_json','updated_at'],
    'subscriptions' => ['user_id','enabled'],
    'notifications' => ['id','user_id','type','payload_json','created_at','sent_at'],
    'translations' => ['id','src_lang','dst_lang','src_hash','src_text','dst_text','updated_at'],
];

if ($onlyTables) { $tables = array_intersect_key($tables, array_flip($onlyTables)); }
if (!$tables) { outln('[INFO] No tables selected.'); exit(0); }

outln('Source (SQLite): ' . $src);
outln('Target (MySQL): ' . (getenv('RC_MYSQL_DB') ?: 'configured in Config'));
outln('Prefix: ' . $prefix);
outln('Dry run: ' . ($dry ? 'yes' : 'no'));

$summary = [];
foreach ($tables as $base => $cols) {
    $srcName = srcTable($srcPdo, $base, $prefix);
    $dstName = Database::t($base);
    if ($srcName === null) { outln('[SKIP] Source table not found: ' . $base . ' (tried ' . $prefix . $base . ' and ' . $base . ')'); continue; }
    // Intersect columns with what actually exists in source
    $srcCols = sqliteColumns($srcPdo, $srcName);
    $useCols = array_values(array_intersect($cols, $srcCols));
    if (!$useCols) { outln('[SKIP] No matching columns in source for ' . $base); continue; }
    outln('[COPY] ' . $srcName . ' -> ' . $dstName . ' (' . implode(',', $useCols) . ')');
    $res = copyTable($srcPdo, $destPdo, $srcName, $dstName, $useCols, $limit, (bool)$dry);
    outln('       copied=' . $res['copied'] . ' errors=' . $res['errors']);
    $summary[$base] = $res;
}

outln("\nDone.");
foreach ($summary as $t => $res) { outln('- ' . $t . ': copied=' . $res['copied'] . ', errors=' . $res['errors']); }
