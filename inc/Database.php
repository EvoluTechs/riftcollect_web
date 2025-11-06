<?php
namespace RiftCollect;

use PDO; use PDOException; use RuntimeException; use Exception;

final class Database
{
    private static ?PDO $pdo = null;
    private const LOCAL_IMG_EXTS = ['jpg','jpeg','png','webp','gif'];

    public static function t(string $name): string { return Config::$DB_TABLE_PREFIX . $name; }

    private static function localImageUrlForId(string $cardId, ?string $setCode = null): ?string
    {
        // Expect IDs like OGN-030; pad to 4 digits as saved by cron: card-0030.ext
        if ($setCode === null || $setCode === '') {
            if (preg_match('/^([A-Za-z0-9]+)-/', $cardId, $m)) $setCode = $m[1]; else $setCode = '';
        }
        // Support optional variant suffix (e.g., OGN-007a)
        if (!preg_match('/-(\d{1,4})([a-z])?$/i', $cardId, $n)) return null;
        $num4 = str_pad($n[1], 4, '0', STR_PAD_LEFT);
        $suffix = isset($n[2]) ? strtolower($n[2]) : '';
        $baseDir = dirname(__DIR__) . '/assets/img/cards/' . $setCode;
        $baseUrlNoExt = 'assets/img/cards/' . rawurlencode($setCode) . '/card-' . $num4 . $suffix;
        foreach (self::LOCAL_IMG_EXTS as $ext) {
            $ext2 = strtolower($ext) === 'jpeg' ? 'jpg' : $ext;
            $p = $baseDir . '/card-' . $num4 . $suffix . '.' . $ext2;
            if (is_file($p)) return $baseUrlNoExt . '.' . $ext2;
        }
        return null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            if (Config::$DB_DRIVER === 'sqlite') {
                // Ensure directory exists for sqlite file
                if (str_starts_with(Config::$DB_DSN, 'sqlite:')) {
                    $path = substr(Config::$DB_DSN, 7);
                    $dir = dirname($path);
                    if (!is_dir($dir)) @mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO(Config::$DB_DSN, null, null, $opts);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                // For MySQL, emulated prepares can help with LIMIT/OFFSET binding
                $opts[PDO::ATTR_EMULATE_PREPARES] = false;
                self::$pdo = new PDO(Config::$DB_DSN, Config::$DB_USER, Config::$DB_PASS, $opts);
            }
        } catch (PDOException $e) {
            // Automatic fallback: if MySQL connection fails, try local SQLite store under storage/
            $primaryErr = $e->getMessage();
            if (Config::$DB_DRIVER !== 'sqlite') {
                try {
                    $sqliteFile = Config::$STORAGE_PATH . '/riftcollect.sqlite';
                    if (!is_dir(dirname($sqliteFile))) @mkdir(dirname($sqliteFile), 0775, true);
                    Config::$DB_DRIVER = 'sqlite';
                    Config::$DB_DSN = 'sqlite:' . $sqliteFile;
                    Config::$DB_USER = null; Config::$DB_PASS = null;
                    self::$pdo = new PDO(Config::$DB_DSN, null, null, $opts);
                    self::$pdo->exec('PRAGMA foreign_keys = ON;');
                } catch (PDOException $e2) {
                    throw new RuntimeException('DB connection failed (primary=' . $primaryErr . ', fallback=' . $e2->getMessage() . ')');
                }
            } else {
                throw new RuntimeException('DB connection failed: ' . $primaryErr);
            }
        }
        self::migrate();
        return self::$pdo;
    }

    public static function instance(): PDO
    {
        return self::pdo();
    }

    private static function migrate(): void
    {
        $db = self::$pdo;
        if (Config::$DB_DRIVER === 'sqlite') {
            // SQLite schema (prefixed)
            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('users') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('cards_cache') . ' (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                rarity TEXT,
                set_code TEXT,
                image_url TEXT,
                data_json TEXT,
                updated_at INTEGER NOT NULL,
                color TEXT,
                card_type TEXT,
                description TEXT
            )');
            // backward-compatible column adds (SQLite only)
            try {
                $cols = [];
                $rs = $db->query('PRAGMA table_info(' . self::t('cards_cache') . ')');
                if ($rs) {
                    foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['name'])] = true; }
                }
                if (!isset($cols['color'])) {
                    $db->exec('ALTER TABLE ' . self::t('cards_cache') . ' ADD COLUMN color TEXT');
                }
                if (!isset($cols['card_type'])) {
                    $db->exec('ALTER TABLE ' . self::t('cards_cache') . ' ADD COLUMN card_type TEXT');
                }
                if (!isset($cols['description'])) {
                    $db->exec('ALTER TABLE ' . self::t('cards_cache') . ' ADD COLUMN description TEXT');
                }
            } catch (Exception $e) { /* ignore */ }
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_name') . ' ON ' . self::t('cards_cache') . '(name)');
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_set') . ' ON ' . self::t('cards_cache') . '(set_code)');
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_rarity') . ' ON ' . self::t('cards_cache') . '(rarity)');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('collections') . ' (
                user_id INTEGER NOT NULL,
                card_id TEXT NOT NULL,
                qty INTEGER NOT NULL,
                PRIMARY KEY (user_id, card_id),
                FOREIGN KEY (user_id) REFERENCES ' . self::t('users') . '(id) ON DELETE CASCADE
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('expansions') . ' (
                code TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                released_at INTEGER,
                data_json TEXT,
                updated_at INTEGER NOT NULL
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('subscriptions') . ' (
                user_id INTEGER PRIMARY KEY,
                enabled INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (user_id) REFERENCES ' . self::t('users') . '(id) ON DELETE CASCADE
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('notifications') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                sent_at INTEGER NULL,
                FOREIGN KEY (user_id) REFERENCES ' . self::t('users') . '(id) ON DELETE CASCADE
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('translations') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                src_lang TEXT,
                dst_lang TEXT,
                src_hash TEXT UNIQUE,
                src_text TEXT NOT NULL,
                dst_text TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )');
        } else {
            // MySQL schema (InnoDB, utf8mb4)
            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('users') . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('cards_cache') . '` (
                `id` VARCHAR(32) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `rarity` VARCHAR(32) NULL,
                `set_code` VARCHAR(32) NULL,
                `image_url` TEXT NULL,
                `data_json` LONGTEXT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                `color` VARCHAR(32) NULL,
                `card_type` VARCHAR(64) NULL,
                `description` TEXT NULL,
                PRIMARY KEY (`id`),
                KEY `' . self::t('idx_cards_name') . '` (`name`),
                KEY `' . self::t('idx_cards_set') . '` (`set_code`),
                KEY `' . self::t('idx_cards_rarity') . '` (`rarity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('collections') . '` (
                `user_id` INT UNSIGNED NOT NULL,
                `card_id` VARCHAR(32) NOT NULL,
                `qty` INT NOT NULL,
                PRIMARY KEY (`user_id`, `card_id`),
                CONSTRAINT `fk_' . self::t('collections') . '_user` FOREIGN KEY (`user_id`) REFERENCES `' . self::t('users') . '`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('expansions') . '` (
                `code` VARCHAR(32) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `released_at` INT UNSIGNED NULL,
                `data_json` LONGTEXT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('subscriptions') . '` (
                `user_id` INT UNSIGNED NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`user_id`),
                CONSTRAINT `fk_' . self::t('subscriptions') . '_user` FOREIGN KEY (`user_id`) REFERENCES `' . self::t('users') . '`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('notifications') . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `type` VARCHAR(64) NOT NULL,
                `payload_json` LONGTEXT NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `sent_at` INT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_' . self::t('notifications') . '_user` FOREIGN KEY (`user_id`) REFERENCES `' . self::t('users') . '`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('translations') . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `src_lang` VARCHAR(16) NULL,
                `dst_lang` VARCHAR(16) NULL,
                `src_hash` VARCHAR(64) NOT NULL UNIQUE,
                `src_text` TEXT NOT NULL,
                `dst_text` LONGTEXT NOT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    // -------- Cards & API --------

    private static function http_get_json(string $url): array
    {
        $headers = ["Accept: application/json"];
        if (Config::$API_KEY) $headers[] = 'Authorization: Bearer ' . Config::$API_KEY;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP error: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($res, true);
        if ($code >= 400) throw new RuntimeException('API error ' . $code . ': ' . ($json['message'] ?? ''));
        return is_array($json) ? $json : [];
    }

    private static function http_post_json(string $url, array $payload, array $headers = []): array
    {
        $baseHeaders = ["Accept: application/json", "Content-Type: application/json"];
        $headers = array_merge($baseHeaders, $headers);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP POST error: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($res, true);
        if ($code >= 400) throw new RuntimeException('API POST error ' . $code . ': ' . ($json['message'] ?? ''));
        return is_array($json) ? $json : [];
    }

    // Public wrapper to reuse translation from other scripts (cron)
    public static function translateText(string $text, string $dst = 'fr-FR'): string
    {
        return self::translate_text($text, $dst);
    }

    private static function translate_text(string $text, string $dst = 'fr-FR'): string
    {
        $t = trim($text);
        if ($t === '') return $t;
        $db = self::pdo();
        $hash = hash('sha256', $dst . "\n" . $t);
    $sel = $db->prepare('SELECT dst_text FROM ' . self::t('translations') . ' WHERE src_hash = ? LIMIT 1');
        $sel->execute([$hash]);
        $row = $sel->fetch();
        if ($row && isset($row['dst_text'])) return (string)$row['dst_text'];
        // No cache or miss — call LLM if configured
        $base = Config::$LLM_BASE_URL ?: 'https://api.openai.com/v1';
        $key = Config::$OPENAI_API_KEY ?: '';
        $model = Config::$LLM_MODEL ?: 'gpt-4o-mini';
        if ($key === '') return $t; // cannot translate without key
        $url = rtrim($base, '/') . '/chat/completions';
        $sys = 'You are a translation engine. Translate the user text into French. If the text is already in French, return it unchanged. Keep formatting, numbers, and game-specific proper nouns as-is when appropriate. Return only the translated text.';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $t],
            ],
            'temperature' => 0.2,
        ];
        try {
            $resp = self::http_post_json($url, $payload, ['Authorization: Bearer ' . $key]);
            $out = (string)($resp['choices'][0]['message']['content'] ?? $t);
            $sql = (Config::$DB_DRIVER === 'sqlite')
                ? ('INSERT OR REPLACE INTO ' . self::t('translations') . ' (src_lang, dst_lang, src_hash, src_text, dst_text, updated_at) VALUES (?,?,?,?,?,?)')
                : ('REPLACE INTO ' . self::t('translations') . ' (src_lang, dst_lang, src_hash, src_text, dst_text, updated_at) VALUES (?,?,?,?,?,?)');
            $ins = $db->prepare($sql);
            $ins->execute([null, $dst, $hash, $t, $out, time()]);
            return $out;
        } catch (Exception $e) {
            return $t; // fallback
        }
    }

    public static function syncCardsFromApi(): int
    {
        $db = self::pdo();
        // NOTE: Placeholder endpoint. Adjust in Config to real Riftbound API.
        $url = rtrim(Config::$API_BASE_URL, '/') . '/cards?locale=fr-FR';
        $data = self::http_get_json($url);
        $cards = $data['cards'] ?? $data ?? [];
        if (!is_array($cards)) $cards = [];
        $now = time();
    $insert = $db->prepare('REPLACE INTO ' . self::t('cards_cache') . ' (id,name,rarity,set_code,image_url,data_json,updated_at) VALUES (?,?,?,?,?,?,?)');
        $count = 0;
        foreach ($cards as $c) {
            $id = (string)($c['id'] ?? $c['card_id'] ?? '');
            if ($id === '') continue;
            $name = (string)($c['name'] ?? '');
            $rarity = (string)($c['rarity'] ?? '');
            $set = (string)($c['set'] ?? $c['set_code'] ?? '');
            $image = (string)($c['image'] ?? $c['image_url'] ?? '');
            $insert->execute([$id, $name, $rarity, $set, $image, json_encode($c, JSON_UNESCAPED_UNICODE), $now]);
            $count++;
        }
        return $count;
    }

    public static function cardsList(string $q, string $rarity, string $set, int $page, int $pageSize, string $color = '', string $cardType = ''): array
    {
        $db = self::pdo();
        $where = [];
        $params = [];
        // helpers for normalization
        $normalizeBasic = function (string $s): string {
            $s = strtolower(trim($s));
            $s = str_replace(['_', '-'], ' ', $s);
            // minimal accent removal for expected words
            $s = strtr($s, [
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'à' => 'a', 'â' => 'a',
                'î' => 'i', 'ï' => 'i',
                'ô' => 'o', 'ö' => 'o',
                'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                'ç' => 'c',
            ]);
            $s = preg_replace('/\s+/', ' ', $s) ?: $s;
            return $s;
        };
            $rarityMatchValues = function (string $input) use ($normalizeBasic): array {
            if ($input === '') return [];
            $k = $normalizeBasic($input);
            // map many variants to canonical
            $toCanonical = [
                'commune' => 'common', 'common' => 'common',
                'peu commune' => 'uncommon', 'peu commune ' => 'uncommon', 'peu  commune' => 'uncommon', 'uncommon' => 'uncommon',
                'peu_commune' => 'uncommon', 'peu-commune' => 'uncommon',
                'rare' => 'rare',
                'epique' => 'epic', 'epic' => 'epic',
                    'legendaire' => 'legendary', 'legendary' => 'legendary',
                    // alternatives
                    'overnumber' => 'overnumbered', 'over-number' => 'overnumbered', 'overnumbered' => 'overnumbered',
            ];
            $canon = $toCanonical[$k] ?? $k; // if unknown, keep as-is
            // build set of acceptable DB values (lowercased)
            $vals = [];
            $vals[$canon] = true;
            if ($canon === 'common') { $vals['commune'] = true; }
            if ($canon === 'uncommon') { $vals['peu commune'] = true; $vals['peu_commune'] = true; $vals['peu-commune'] = true; }
            if ($canon === 'epic') { $vals['epique'] = true; }
            if ($canon === 'legendary') { $vals['legendaire'] = true; $vals['légendaire'] = true; }
                if ($canon === 'overnumbered') { $vals['overnumber'] = true; $vals['over-number'] = true; }
            // also include the original input variants
            $vals[$k] = true;
            return array_keys($vals);
        };
        if ($q !== '') {
            // Search by name OR by id (e.g., "OGN-030")
            $where[] = '(name LIKE :q OR id LIKE :qid)';
            $params[':q'] = '%' . $q . '%';
            $params[':qid'] = '%' . strtoupper($q) . '%';
        }
        if ($rarity !== '') {
            // Normalize rarity keys (accept FR/EN synonyms & separators) and compare against a set of variants
            $vals = $rarityMatchValues($rarity);
            $conds = [];
            $i = 0;
            foreach ($vals as $v) {
                $ph = ':r' . $i++;
                $conds[] = "LOWER(rarity) = LOWER($ph)";
                $params[$ph] = $v;
            }
            // Also try inside JSON payload if present
            $where[] = '(
                ' . implode(' OR ', $conds) . '
                OR (data_json IS NOT NULL AND (
                    ' . implode(' OR ', array_map(function($idx){ return "LOWER(data_json) LIKE :rj$idx"; }, array_keys($vals))) . '
                ))
            )';
            // Bind JSON LIKE placeholders
            $j = 0;
            foreach ($vals as $v) {
                $params[':rj' . $j++] = '%"rarity":"' . strtolower($v) . '"%';
            }
        }
        if ($set !== '') {
            // Allow filtering by set code or expansion name
            $resolved = $set;
            try {
                $st = $db->prepare('SELECT code FROM ' . self::t('expansions') . ' WHERE code = :c OR LOWER(name) = LOWER(:n) LIMIT 1');
                $st->execute([':c' => strtoupper($set), ':n' => $set]);
                $row = $st->fetch();
                if ($row && !empty($row['code'])) $resolved = (string)$row['code'];
            } catch (Exception $e) { /* ignore, fallback to raw */ }
            $where[] = 'set_code = :s';
            $params[':s'] = $resolved;
        }
        if ($color !== '') {
            // Normalize color keys and accept common synonyms
            $map = [
                'body' => 'body',
                'calm' => 'calm',
                'chaos' => 'chaos',
                'colorless' => 'colorless', 'neutral' => 'colorless', 'none' => 'colorless',
                'fury' => 'fury',
                'mind' => 'mind',
                'order' => 'order',
            ];
            $ckey = strtolower($color);
            $c = $map[$ckey] ?? $ckey;
            // Match column OR fallback string match on JSON payload (allow minified or spaced, and array under "colors")
            $where[] = '(
                LOWER(color) = :colorLower
                OR (data_json IS NOT NULL AND (
                    LOWER(data_json) LIKE :colorJson1
                    OR LOWER(data_json) LIKE :colorJson2
                    OR LOWER(data_json) LIKE :colorJson3
                ))
            )';
            $params[':colorLower'] = $c;
            $params[':colorJson1'] = '%"color":"' . $c . '"%';
            $params[':colorJson2'] = '%"color"%:%"' . $c . '"%';
            $params[':colorJson3'] = '%"colors"%:%"' . $c . '"%';
        }
        if ($cardType !== '') {
            $ct = strtolower($cardType);
            // Accept both card_type and type keys inside JSON
            $where[] = '(LOWER(card_type) = :ctypeLower OR (data_json IS NOT NULL AND (LOWER(data_json) LIKE :ctypeJson1 OR LOWER(data_json) LIKE :ctypeJson2)))';
            $params[':ctypeLower'] = $ct;
            $params[':ctypeJson1'] = '%"card_type":"' . $ct . '"%';
            $params[':ctypeJson2'] = '%"type":"' . $ct . '"%';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $pageSize;
        // NOTE: first COUNT execute result was not used correctly; kept for compatibility but compute real count below
        $db->prepare('SELECT COUNT(*) FROM ' . self::t('cards_cache') . ' ' . $whereSql)->execute($params);
        // Order by set then by numeric part of the card id (e.g., 'OGN-030' -> 30), fallback to id
        $numExpr = (Config::$DB_DRIVER === 'mysql')
            ? "CAST(SUBSTRING(id, INSTR(id, '-')+1) AS UNSIGNED)"
            : "CAST(substr(id, instr(id, '-')+1) AS INTEGER)";
        $stmt = $db->prepare('SELECT id,name,rarity,set_code,image_url,color,card_type,data_json FROM ' . self::t('cards_cache') . ' ' . $whereSql . ' ORDER BY set_code ASC, ' . $numExpr . ' ASC, id ASC LIMIT :limit OFFSET :offset');
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        // Prefer locally cached image if available for faster loading
        foreach ($items as &$it) {
            $loc = self::localImageUrlForId((string)($it['id'] ?? ''), (string)($it['set_code'] ?? ''));
            if ($loc) $it['image_url'] = $loc;
            // Derive color/type from data_json if missing (best-effort, no DB write here)
            $dj = isset($it['data_json']) ? strtolower((string)$it['data_json']) : '';
            if ((empty($it['color']) || $it['color'] === null) && $dj !== '') {
                if (preg_match('/"color"\s*:\s*"([a-z]+)"/i', $dj, $m)) { $it['color'] = $m[1]; }
            }
            if ((empty($it['card_type']) || $it['card_type'] === null) && $dj !== '') {
                if (preg_match('/"card_type"\s*:\s*"([a-z]+)"/i', $dj, $m)) { $it['card_type'] = $m[1]; }
                elseif (preg_match('/"type"\s*:\s*"([a-z]+)"/i', $dj, $m)) { $it['card_type'] = $m[1]; }
            }
        }
        unset($it);
        // If empty and truly no filters, try initial sync (best-effort, swallow network errors)
        if ((count($items) === 0) && $q === '' && $rarity === '' && $set === '' && $color === '' && $cardType === '') {
            try { self::syncCardsFromApi(); } catch (\Throwable $e) { /* ignore network/API failures */ }
            return self::cardsList($q, $rarity, $set, $page, $pageSize, $color, $cardType);
        }
        // Fetch total again properly
        $stmt2 = $db->prepare('SELECT COUNT(*) AS c FROM ' . self::t('cards_cache') . ' ' . $whereSql);
        foreach ($params as $k => $v) $stmt2->bindValue($k, $v);
        $stmt2->execute();
        $row = $stmt2->fetch();
        $total = (int)($row['c'] ?? 0);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public static function cardDetail(string $id, string $locale = 'fr-FR'): ?array
    {
        $db = self::pdo();
    $stmt = $db->prepare('SELECT * FROM ' . self::t('cards_cache') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;
        $want = strtolower($locale);
        // If French requested or no locale specified: serve from DB, fallback to fr-FR fetch + persist
        if ($want === '' || strpos($want, 'fr') === 0) {
            if ($row) {
                $loc = self::localImageUrlForId((string)$row['id'], (string)($row['set_code'] ?? ''));
                if ($loc) $row['image_url'] = $loc;
                // Auto-translate key fields to French if needed (non-destructive)
                $row['name'] = self::translate_text((string)($row['name'] ?? ''), 'fr-FR');
                if (!empty($row['description'])) $row['description'] = self::translate_text((string)$row['description'], 'fr-FR');
                if (!empty($row['data_json'])) {
                    $j = json_decode((string)$row['data_json'], true);
                    if (is_array($j)) {
                        if (isset($j['card']) && is_array($j['card'])) {
                            if (isset($j['card']['effect'])) $j['card']['effect'] = self::translate_text((string)$j['card']['effect'], 'fr-FR');
                            if (isset($j['card']['flavor_text'])) $j['card']['flavor_text'] = self::translate_text((string)$j['card']['flavor_text'], 'fr-FR');
                        } else {
                            if (isset($j['effect'])) $j['effect'] = self::translate_text((string)$j['effect'], 'fr-FR');
                            if (isset($j['flavor_text'])) $j['flavor_text'] = self::translate_text((string)$j['flavor_text'], 'fr-FR');
                        }
                        $row['data_json'] = json_encode($j, JSON_UNESCAPED_UNICODE);
                    }
                }
                return $row;
            }
            // try fetch FR and persist
            $url = rtrim(Config::$API_BASE_URL, '/') . '/cards/' . rawurlencode($id) . '?locale=fr-FR';
            try { $data = self::http_get_json($url); } catch (Exception $e) { return null; }
            if (!$data) return null;
            $c = $data['card'] ?? $data;
            $now = time();
            $cid = (string)($c['id'] ?? $c['card_id'] ?? $id);
            $name = (string)($c['name'] ?? '');
            $rarity = (string)($c['rarity'] ?? '');
            $set = (string)($c['set'] ?? $c['set_code'] ?? '');
            $image = (string)($c['image'] ?? $c['image_url'] ?? '');
            $ins = $db->prepare('REPLACE INTO ' . self::t('cards_cache') . ' (id,name,rarity,set_code,image_url,data_json,updated_at) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$cid, $name, $rarity, $set, $image, json_encode($c, JSON_UNESCAPED_UNICODE), $now]);
            $stmt = $db->prepare('SELECT * FROM ' . self::t('cards_cache') . ' WHERE id = ?');
            $stmt->execute([$cid]);
            $row = $stmt->fetch() ?: null;
            if ($row) {
                $loc = self::localImageUrlForId((string)$row['id'], (string)($row['set_code'] ?? ''));
                if ($loc) $row['image_url'] = $loc;
                // Translate to French on return (DB remains source-of-truth)
                $row['name'] = self::translate_text((string)($row['name'] ?? ''), 'fr-FR');
                if (!empty($row['description'])) $row['description'] = self::translate_text((string)$row['description'], 'fr-FR');
                if (!empty($row['data_json'])) {
                    $j = json_decode((string)$row['data_json'], true);
                    if (is_array($j)) {
                        if (isset($j['card']) && is_array($j['card'])) {
                            if (isset($j['card']['effect'])) $j['card']['effect'] = self::translate_text((string)$j['card']['effect'], 'fr-FR');
                            if (isset($j['card']['flavor_text'])) $j['card']['flavor_text'] = self::translate_text((string)$j['card']['flavor_text'], 'fr-FR');
                        } else {
                            if (isset($j['effect'])) $j['effect'] = self::translate_text((string)$j['effect'], 'fr-FR');
                            if (isset($j['flavor_text'])) $j['flavor_text'] = self::translate_text((string)$j['flavor_text'], 'fr-FR');
                        }
                        $row['data_json'] = json_encode($j, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            return $row;
        }
        // For non-FR (e.g., EN), fetch from API and merge with DB (do not persist to avoid overwriting FR cache)
        $url = rtrim(Config::$API_BASE_URL, '/') . '/cards/' . rawurlencode($id) . '?locale=' . rawurlencode($locale);
        try { $data = self::http_get_json($url); } catch (Exception $e) { $data = null; }
        if (!$row && !$data) return null;
        $base = $row ?: [];
        $locUrl = null;
        if (!empty($base['id'])) {
            $locUrl = self::localImageUrlForId((string)$base['id'], (string)($base['set_code'] ?? ''));
        }
        if ($data) {
            $c = $data['card'] ?? $data;
            // Merge: prefer localized name/description/data_json from remote; keep stable fields from DB if present
            $base['id'] = (string)($base['id'] ?? ($c['id'] ?? $c['card_id'] ?? $id));
            $base['name'] = (string)($c['name'] ?? ($base['name'] ?? ''));
            if (isset($c['description'])) $base['description'] = (string)$c['description'];
            $base['rarity'] = (string)($base['rarity'] ?? ($c['rarity'] ?? ''));
            $base['set_code'] = (string)($base['set_code'] ?? ($c['set'] ?? $c['set_code'] ?? ''));
            $base['image_url'] = (string)($base['image_url'] ?? ($c['image'] ?? $c['image_url'] ?? ''));
            $base['data_json'] = json_encode($c, JSON_UNESCAPED_UNICODE);
        }
        if ($locUrl) $base['image_url'] = $locUrl;
        return $base ?: null;
    }

    // -------- Collection --------

    public static function collectionGet(int $userId): array
    {
        $db = self::pdo();
    $stmt = $db->prepare('SELECT c.card_id, c.qty, k.name, k.rarity, k.set_code, k.image_url, k.color, k.card_type FROM ' . self::t('collections') . ' c LEFT JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? ORDER BY k.name');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $id = (string)($r['card_id'] ?? '');
            $set = (string)($r['set_code'] ?? '');
            $loc = self::localImageUrlForId($id, $set);
            if ($loc) $r['image_url'] = $loc;
        }
        unset($r);
        return $rows;
    }

    public static function collectionSet(int $userId, string $cardId, int $qty): void
    {
        $db = self::pdo();
        if ($qty <= 0) {
            $del = $db->prepare('DELETE FROM ' . self::t('collections') . ' WHERE user_id = ? AND card_id = ?');
            $del->execute([$userId, $cardId]);
            return;
        }
        $stmt = $db->prepare('REPLACE INTO ' . self::t('collections') . ' (user_id,card_id,qty) VALUES (?,?,?)');
        $stmt->execute([$userId, $cardId, $qty]);
    }

    public static function collectionBulkSet(int $userId, array $items): int
    {
        $db = self::pdo();
        $db->beginTransaction();
        try {
            $count = 0;
            $ins = $db->prepare('REPLACE INTO ' . self::t('collections') . ' (user_id,card_id,qty) VALUES (?,?,?)');
            $del = $db->prepare('DELETE FROM ' . self::t('collections') . ' WHERE user_id = ? AND card_id = ?');
            foreach ($items as $it) {
                $cardId = (string)($it['card_id'] ?? '');
                $qty = (int)($it['qty'] ?? 0);
                if ($cardId === '') continue;
                if ($qty <= 0) {
                    $del->execute([$userId, $cardId]);
                } else {
                    $ins->execute([$userId, $cardId, $qty]);
                }
                $count++;
            }
            $db->commit();
            return $count;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function statsProgress(int $userId): array
    {
        $db = self::pdo();
        // totals per rarity
    $rar = $db->query('SELECT rarity, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY rarity')->fetchAll();
    $ownR = $db->prepare('SELECT k.rarity AS rarity, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.rarity');
        $ownR->execute([$userId]);
        $ownedR = $ownR->fetchAll();
        $rarMap = [];
        foreach ($rar as $r) $rarMap[$r['rarity'] ?: ''] = (int)$r['total'];
        $ownedMap = [];
        foreach ($ownedR as $r) $ownedMap[$r['rarity'] ?: ''] = (int)$r['owned'];
        $rarities = [];
        foreach ($rarMap as $k => $total) {
            $o = $ownedMap[$k] ?? 0;
            $rarities[] = ['rarity' => $k, 'owned' => $o, 'total' => $total, 'percent' => $total ? round($o*100/$total, 1) : 0.0];
        }
        // totals per set
    $setTot = $db->query('SELECT set_code, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY set_code')->fetchAll();
    $setOwn = $db->prepare('SELECT k.set_code, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.set_code');
        $setOwn->execute([$userId]);
        $setOwned = $setOwn->fetchAll();
        $setMap = [];
        foreach ($setTot as $s) $setMap[$s['set_code'] ?: ''] = (int)$s['total'];
        $setOwnedMap = [];
        foreach ($setOwned as $s) $setOwnedMap[$s['set_code'] ?: ''] = (int)$s['owned'];
        $sets = [];
        foreach ($setMap as $k => $total) {
            $o = $setOwnedMap[$k] ?? 0;
            $sets[] = ['set' => $k, 'owned' => $o, 'total' => $total, 'percent' => $total ? round($o*100/$total, 1) : 0.0];
        }
        // global
    $t = (int)$db->query('SELECT COUNT(*) FROM ' . self::t('cards_cache'))->fetchColumn();
    $stmt = $db->prepare('SELECT COUNT(*) FROM ' . self::t('collections') . ' WHERE user_id = ? AND qty > 0');
        $stmt->execute([$userId]);
        $o = (int)$stmt->fetchColumn();
        return [
            'global' => ['owned' => $o, 'total' => $t, 'percent' => $t ? round($o*100/$t, 1) : 0.0],
            'byRarity' => $rarities,
            'bySet' => $sets,
        ];
    }

    // -------- Expansions --------

    public static function syncExpansionsFromApi(): int
    {
        $db = self::pdo();
        $url = rtrim(Config::$API_BASE_URL, '/') . '/expansions?locale=fr-FR';
        $data = self::http_get_json($url);
        $list = $data['expansions'] ?? $data ?? [];
        if (!is_array($list)) $list = [];
        $now = time();
    $ins = $db->prepare('REPLACE INTO ' . self::t('expansions') . ' (code,name,released_at,data_json,updated_at) VALUES (?,?,?,?,?)');
        $count = 0;
        foreach ($list as $e) {
            $code = (string)($e['code'] ?? $e['id'] ?? '');
            if ($code === '') continue;
            $name = (string)($e['name'] ?? '');
            $released = isset($e['released_at']) ? (int)strtotime((string)$e['released_at']) : null;
            $ins->execute([$code, $name, $released, json_encode($e, JSON_UNESCAPED_UNICODE), $now]);
            $count++;
        }
        return $count;
    }

    public static function expansionsList(): array
    {
        $db = self::pdo();
        if (Config::$DB_DRIVER === 'mysql') {
            $stmt = $db->query('SELECT code, name, released_at, updated_at FROM ' . self::t('expansions') . ' ORDER BY (released_at IS NULL), released_at DESC');
        } else {
            $stmt = $db->query('SELECT code, name, released_at, updated_at FROM ' . self::t('expansions') . ' ORDER BY released_at DESC NULLS LAST');
        }
        $rows = $stmt->fetchAll();
        if (!$rows) {
            self::syncExpansionsFromApi();
            $stmt = $db->query('SELECT code, name, released_at, updated_at FROM ' . self::t('expansions') . ' ORDER BY released_at DESC');
            $rows = $stmt->fetchAll();
        }
        return $rows ?: [];
    }

    public static function subscriptionSet(int $userId, int $enabled): void
    {
        $db = self::pdo();
        $stmt = $db->prepare('REPLACE INTO ' . self::t('subscriptions') . ' (user_id, enabled) VALUES (?,?)');
        $stmt->execute([$userId, $enabled ? 1 : 0]);
    }
}
