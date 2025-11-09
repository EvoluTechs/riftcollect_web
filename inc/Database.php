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
                display_name TEXT NULL,
                password_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                share_enabled INTEGER NOT NULL DEFAULT 0,
                share_token TEXT NULL
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
                description TEXT,
                price REAL
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
                if (!isset($cols['price'])) {
                    $db->exec('ALTER TABLE ' . self::t('cards_cache') . ' ADD COLUMN price REAL');
                }
            } catch (Exception $e) { /* ignore */ }
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_name') . ' ON ' . self::t('cards_cache') . '(name)');
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_set') . ' ON ' . self::t('cards_cache') . '(set_code)');
            $db->exec('CREATE INDEX IF NOT EXISTS ' . self::t('idx_cards_rarity') . ' ON ' . self::t('cards_cache') . '(rarity)');

            // Articles table
            $db->exec('CREATE TABLE IF NOT EXISTS ' . self::t('articles') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                subtitle TEXT NULL,
                slug TEXT NOT NULL UNIQUE,
                content TEXT NOT NULL,
                author_id INTEGER NOT NULL,
                published INTEGER NOT NULL DEFAULT 0,
                is_guide INTEGER NOT NULL DEFAULT 0,
                redacteur TEXT NULL,
                source TEXT NULL,
                image_url TEXT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                FOREIGN KEY (author_id) REFERENCES ' . self::t('users') . '(id) ON DELETE CASCADE
            )');
            // Backward-compatible adds for new article columns
            try {
                $cols = [];
                $rs = $db->query('PRAGMA table_info(' . self::t('articles') . ')');
                if ($rs) { foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['name'])] = true; } }
                if (!isset($cols['subtitle'])) { $db->exec('ALTER TABLE ' . self::t('articles') . ' ADD COLUMN subtitle TEXT NULL'); }
                if (!isset($cols['is_guide'])) { $db->exec('ALTER TABLE ' . self::t('articles') . ' ADD COLUMN is_guide INTEGER NOT NULL DEFAULT 0'); }
                if (!isset($cols['redacteur'])) { $db->exec('ALTER TABLE ' . self::t('articles') . ' ADD COLUMN redacteur TEXT NULL'); }
                if (!isset($cols['source'])) { $db->exec('ALTER TABLE ' . self::t('articles') . ' ADD COLUMN source TEXT NULL'); }
                if (!isset($cols['image_url'])) { $db->exec('ALTER TABLE ' . self::t('articles') . ' ADD COLUMN image_url TEXT NULL'); }
            } catch (\Throwable $e) { /* ignore */ }

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
            // Backward-compatible column adds for users (older installs)
            try {
                $cols = [];
                $rs = $db->query('PRAGMA table_info(' . self::t('users') . ')');
                if ($rs) { foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['name'])] = true; } }
                if (!isset($cols['display_name'])) {
                    $db->exec('ALTER TABLE ' . self::t('users') . ' ADD COLUMN display_name TEXT NULL');
                }
                if (!isset($cols['share_enabled'])) {
                    $db->exec('ALTER TABLE ' . self::t('users') . ' ADD COLUMN share_enabled INTEGER NOT NULL DEFAULT 0');
                }
                if (!isset($cols['share_token'])) {
                    $db->exec('ALTER TABLE ' . self::t('users') . ' ADD COLUMN share_token TEXT NULL');
                }
                // Unique index for share_token (allow quick lookup)
                $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::t('idx_users_share_token') . ' ON ' . self::t('users') . ' (share_token)');
            } catch (\Throwable $e) { /* ignore */ }
        } else {
            // MySQL schema (InnoDB, utf8mb4)
            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('users') . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `display_name` VARCHAR(255) NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `share_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `share_token` VARCHAR(64) NULL UNIQUE,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            // Backfill display_name on older installs
            try { $db->exec('ALTER TABLE `' . self::t('users') . '` ADD COLUMN `display_name` VARCHAR(255) NULL'); } catch (\Throwable $e) { /* already exists */ }

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
                `price` DECIMAL(10,2) NULL,
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

            // Articles table
            $db->exec('CREATE TABLE IF NOT EXISTS `' . self::t('articles') . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `subtitle` VARCHAR(512) NULL,
                `slug` VARCHAR(255) NOT NULL UNIQUE,
                `content` LONGTEXT NOT NULL,
                `author_id` INT UNSIGNED NOT NULL,
                `published` TINYINT(1) NOT NULL DEFAULT 0,
                `is_guide` TINYINT(1) NOT NULL DEFAULT 0,
                `redacteur` VARCHAR(255) NULL,
                `source` VARCHAR(1024) NULL,
                `image_url` TEXT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                KEY `' . self::t('idx_articles_published') . '` (`published`),
                CONSTRAINT `fk_' . self::t('articles') . '_author` FOREIGN KEY (`author_id`) REFERENCES `' . self::t('users') . '`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            // Backward-compatible add columns if table existed before share feature
            try {
                $cols = [];
                $rs = $db->query('SHOW COLUMNS FROM `' . self::t('users') . '`');
                if ($rs) { foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['Field'])] = true; } }
                $alters = [];
                if (!isset($cols['share_enabled'])) $alters[] = 'ADD COLUMN `share_enabled` TINYINT(1) NOT NULL DEFAULT 0';
                if (!isset($cols['share_token'])) $alters[] = 'ADD COLUMN `share_token` VARCHAR(64) NULL UNIQUE';
                if ($alters) {
                    $db->exec('ALTER TABLE `' . self::t('users') . '` ' . implode(', ', $alters));
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Backward-compatible add price column to cards_cache
            try {
                $cols = [];
                $rs = $db->query('SHOW COLUMNS FROM `' . self::t('cards_cache') . '`');
                if ($rs) { foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['Field'])] = true; } }
                if (!isset($cols['price'])) {
                    $db->exec('ALTER TABLE `' . self::t('cards_cache') . '` ADD COLUMN `price` DECIMAL(10,2) NULL');
                }
            } catch (\Throwable $e) { /* ignore */ }
            // Backward-compatible add new article columns
            try {
                $cols = [];
                $rs = $db->query('SHOW COLUMNS FROM `' . self::t('articles') . '`');
                if ($rs) { foreach ($rs->fetchAll() as $row) { $cols[strtolower((string)$row['Field'])] = true; } }
                $alters = [];
                if (!isset($cols['subtitle'])) $alters[] = 'ADD COLUMN `subtitle` VARCHAR(512) NULL';
                if (!isset($cols['is_guide'])) $alters[] = 'ADD COLUMN `is_guide` TINYINT(1) NOT NULL DEFAULT 0';
                if (!isset($cols['redacteur'])) $alters[] = 'ADD COLUMN `redacteur` VARCHAR(255) NULL';
                if (!isset($cols['source'])) $alters[] = 'ADD COLUMN `source` VARCHAR(1024) NULL';
                if (!isset($cols['image_url'])) $alters[] = 'ADD COLUMN `image_url` TEXT NULL';
                if ($alters) { $db->exec('ALTER TABLE `' . self::t('articles') . '` ' . implode(', ', $alters)); }
            } catch (\Throwable $e) { /* ignore */ }
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

    // -------- Admin: Cards update --------

    /**
     * Update a subset of metadata for a card. Allowed keys: name, rarity, price, color, card_type, description, image_url, set_code
     */
    public static function updateCardMeta(string $id, array $changes): bool
    {
        $id = trim($id);
        if ($id === '') return false;
        if (!$changes) return true;
        $allowed = [
            'name' => true,
            'rarity' => true,
            'price' => true,
            'color' => true,
            'card_type' => true,
            'description' => true,
            'image_url' => true,
            'set_code' => true,
        ];
        $sets = [];
        $params = [];
        foreach ($changes as $k => $v) {
            if (!isset($allowed[$k])) continue;
            $sets[] = "$k = :$k";
            if ($k === 'price') {
                $params[":$k"] = ($v === null || $v === '') ? null : (float)$v;
            } else {
                $params[":$k"] = $v;
            }
        }
        if (!$sets) return false;
        $params[':id'] = $id;
        $db = self::pdo();
        $sql = 'UPDATE ' . self::t('cards_cache') . ' SET ' . implode(', ', $sets) . ', updated_at = :updated WHERE id = :id';
        $params[':updated'] = time();
        $st = $db->prepare($sql);
        return $st->execute($params) === true;
    }

    // -------- Articles --------

    private static function slugify(string $title): string
    {
        $s = strtolower(trim($title));
        $s = strtr($s, [
            'à' => 'a','â' => 'a','ä' => 'a',
            'é' => 'e','è' => 'e','ê' => 'e','ë' => 'e',
            'î' => 'i','ï' => 'i',
            'ô' => 'o','ö' => 'o',
            'ù' => 'u','û' => 'u','ü' => 'u',
            'ç' => 'c',
        ]);
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?: '';
        $s = trim($s, '-');
        if ($s === '') $s = 'article-' . dechex(random_int(0, 0xffff));
        return $s;
    }

    private static function ensureArticlesTable(PDO $db): void
    {
        // no-op here: created in migrate(); this is a safe helper if older DBs exist
    }

    public static function createArticle(string $title, string $content, int $authorId, bool $published, ?string $redacteur = null, ?string $source = null, ?string $imageUrl = null, ?string $subtitle = null, ?int $createdAt = null, bool $isGuide = false): array
    {
        $db = self::pdo();
        $now = time();
        $slug = self::slugify($title);
        // Ensure slug uniqueness by suffixing with -2, -3 if needed
        $base = $slug; $i = 2;
        while (true) {
            $st = $db->prepare('SELECT 1 FROM ' . self::t('articles') . ' WHERE slug = ? LIMIT 1');
            try { $st->execute([$slug]); $row = $st->fetch(); } catch (\Throwable $e) { $row = false; }
            if (!$row) break;
            $slug = $base . '-' . $i++;
            if ($i > 99) { $slug = $base . '-' . bin2hex(random_bytes(2)); break; }
        }
    $sql = 'INSERT INTO ' . self::t('articles') . ' (title, subtitle, slug, content, author_id, published, is_guide, redacteur, source, image_url, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
    $st2 = $db->prepare($sql);
    $created = $createdAt && $createdAt > 0 ? (int)$createdAt : $now;
    $st2->execute([$title, $subtitle, $slug, $content, $authorId, $published ? 1 : 0, $isGuide ? 1 : 0, $redacteur, $source, $imageUrl, $created, $now]);
        $id = (int)$db->lastInsertId();
        return ['id' => $id, 'slug' => $slug];
    }

    public static function updateArticle(int $id, array $fields): bool
    {
        $db = self::pdo();
    $allowed = ['title','subtitle','content','published','is_guide','redacteur','source','image_url','created_at'];
        $sets = [];
        $params = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "$k = :$k";
            if ($k === 'published') $v = $v ? 1 : 0;
            if ($k === 'created_at') $v = (int)$v;
            $params[":$k"] = $v;
        }
        if (!$sets) return false;
        $params[':id'] = $id;
        $params[':updated'] = time();
        $sql = 'UPDATE ' . self::t('articles') . ' SET ' . implode(', ', $sets) . ', updated_at = :updated WHERE id = :id';
        $st = $db->prepare($sql);
        return $st->execute($params) === true;
    }

    public static function deleteArticle(int $id): bool
    {
        $db = self::pdo();
        $st = $db->prepare('DELETE FROM ' . self::t('articles') . ' WHERE id = ?');
        return $st->execute([$id]) === true;
    }

    public static function getArticle(int $id, bool $includeDrafts = false): ?array
    {
        $db = self::pdo();
        if ($includeDrafts) {
            $st = $db->prepare('SELECT * FROM ' . self::t('articles') . ' WHERE id = ?');
            $st->execute([$id]);
        } else {
            $st = $db->prepare('SELECT * FROM ' . self::t('articles') . ' WHERE id = ? AND published = 1');
            $st->execute([$id]);
        }
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function listArticles(int $page = 1, int $pageSize = 20, bool $includeDrafts = false): array
    {
        $db = self::pdo();
        $page = max(1, $page); $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;
        $guideOnly = false; // default; may be overridden by API caller via 4th param (back-compat signature extended below)
        if (func_num_args() >= 4) { $args = func_get_args(); $guideOnly = (bool)$args[3]; }
        if ($includeDrafts) {
            if ($guideOnly) {
                $cnt = $db->query('SELECT COUNT(*) AS n FROM ' . self::t('articles') . ' WHERE is_guide = 1')->fetch();
                $total = (int)($cnt['n'] ?? 0);
                $sql = 'SELECT * FROM ' . self::t('articles') . ' WHERE is_guide = 1 ORDER BY created_at DESC LIMIT :lim OFFSET :off';
            } else {
                $cnt = $db->query('SELECT COUNT(*) AS n FROM ' . self::t('articles'))->fetch();
                $total = (int)($cnt['n'] ?? 0);
                $sql = 'SELECT * FROM ' . self::t('articles') . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off';
            }
            $st = $db->prepare($sql);
        } else {
            if ($guideOnly) {
                $stCnt = $db->prepare('SELECT COUNT(*) AS n FROM ' . self::t('articles') . ' WHERE published = 1 AND is_guide = 1');
                $stCnt->execute(); $rowCnt = $stCnt->fetch(); $total = (int)($rowCnt['n'] ?? 0);
                $sql = 'SELECT * FROM ' . self::t('articles') . ' WHERE published = 1 AND is_guide = 1 ORDER BY created_at DESC LIMIT :lim OFFSET :off';
            } else {
                $stCnt = $db->prepare('SELECT COUNT(*) AS n FROM ' . self::t('articles') . ' WHERE published = 1');
                $stCnt->execute(); $rowCnt = $stCnt->fetch(); $total = (int)($rowCnt['n'] ?? 0);
                $sql = 'SELECT * FROM ' . self::t('articles') . ' WHERE published = 1 ORDER BY created_at DESC LIMIT :lim OFFSET :off';
            }
            $st = $db->prepare($sql);
        }
        $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll() ?: [];
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
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
    $stmt = $db->prepare('SELECT id,name,rarity,set_code,image_url,color,card_type,description,price,data_json FROM ' . self::t('cards_cache') . ' ' . $whereSql . ' ORDER BY set_code ASC, ' . $numExpr . ' ASC, id ASC LIMIT :limit OFFSET :offset');
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
    // Include price so the frontend can display DB override price badges and compute value locally
    $stmt = $db->prepare('SELECT c.card_id, c.qty, k.name, k.rarity, k.set_code, k.image_url, k.color, k.card_type, COALESCE(k.price, 0) AS price FROM ' . self::t('collections') . ' c LEFT JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? ORDER BY k.name');
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

    /**
     * Advanced statistics for a user collection.
     * Returns a richer dataset to power the Stats page.
     */
    public static function statsFull(int $userId, int $missingLimit = 50): array
    {
        $db = self::pdo();

        // Global counts
        $totalCards = (int)$db->query('SELECT COUNT(*) FROM ' . self::t('cards_cache'))->fetchColumn();
        $ownedUnique = 0;
        $stmt = $db->prepare('SELECT COUNT(*) FROM ' . self::t('collections') . ' WHERE user_id = ? AND qty > 0');
        $stmt->execute([$userId]);
        $ownedUnique = (int)$stmt->fetchColumn();

        // Helpers for percent rows
        $mk = function(array $rows, string $kKey, string $kName = 'key') use ($totalCards) {
            $out = [];
            foreach ($rows as $r) {
                $k = (string)($r[$kKey] ?? '');
                $owned = (int)($r['owned'] ?? 0);
                $total = (int)($r['total'] ?? 0);
                $out[] = [
                    $kName => $k,
                    'owned' => $owned,
                    'total' => $total,
                    'percent' => $total ? round($owned * 100.0 / $total, 1) : 0.0,
                ];
            }
            return $out;
        };

        // By rarity
        $rarTot = $db->query('SELECT COALESCE(rarity, "") AS rarity, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY rarity')->fetchAll();
        $rarOwn = $db->prepare('SELECT COALESCE(k.rarity, "") AS rarity, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.rarity');
        $rarOwn->execute([$userId]);
        $mapTotal = [];
        foreach ($rarTot as $r) $mapTotal[$r['rarity']] = (int)$r['total'];
        $mapOwn = [];
        foreach ($rarOwn->fetchAll() as $r) $mapOwn[$r['rarity']] = (int)$r['owned'];
        $byRarity = [];
        foreach ($mapTotal as $k => $tot) {
            $own = $mapOwn[$k] ?? 0;
            $byRarity[] = ['rarity' => $k, 'owned' => $own, 'total' => $tot, 'percent' => $tot ? round($own*100/$tot,1) : 0.0];
        }

        // By set
        $setTot = $db->query('SELECT COALESCE(set_code, "") AS set_code, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY set_code')->fetchAll();
        $setOwn = $db->prepare('SELECT COALESCE(k.set_code, "") AS set_code, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.set_code');
        $setOwn->execute([$userId]);
        $setTotalMap = [];
        foreach ($setTot as $s) $setTotalMap[$s['set_code']] = (int)$s['total'];
        $setOwnMap = [];
        foreach ($setOwn->fetchAll() as $s) $setOwnMap[$s['set_code']] = (int)$s['owned'];
        $bySet = [];
        foreach ($setTotalMap as $k => $tot) {
            $own = $setOwnMap[$k] ?? 0;
            $bySet[] = ['set' => $k, 'owned' => $own, 'total' => $tot, 'percent' => $tot ? round($own*100/$tot,1) : 0.0];
        }

        // By color
        $colTot = $db->query('SELECT COALESCE(color, "") AS color, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY color')->fetchAll();
        $colOwn = $db->prepare('SELECT COALESCE(k.color, "") AS color, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.color');
        $colOwn->execute([$userId]);
        $cTotMap = [];
        foreach ($colTot as $c) $cTotMap[$c['color']] = (int)$c['total'];
        $cOwnMap = [];
        foreach ($colOwn->fetchAll() as $c) $cOwnMap[$c['color']] = (int)$c['owned'];
        $byColor = [];
        foreach ($cTotMap as $k => $tot) {
            $own = $cOwnMap[$k] ?? 0;
            $byColor[] = ['color' => $k, 'owned' => $own, 'total' => $tot, 'percent' => $tot ? round($own*100/$tot,1) : 0.0];
        }

        // By type
        $typTot = $db->query('SELECT COALESCE(card_type, "") AS card_type, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY card_type')->fetchAll();
        $typOwn = $db->prepare('SELECT COALESCE(k.card_type, "") AS card_type, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.card_type');
        $typOwn->execute([$userId]);
        $tTotMap = [];
        foreach ($typTot as $t) $tTotMap[$t['card_type']] = (int)$t['total'];
        $tOwnMap = [];
        foreach ($typOwn->fetchAll() as $t) $tOwnMap[$t['card_type']] = (int)$t['owned'];
        $byType = [];
        foreach ($tTotMap as $k => $tot) {
            $own = $tOwnMap[$k] ?? 0;
            $byType[] = ['type' => $k, 'owned' => $own, 'total' => $tot, 'percent' => $tot ? round($own*100/$tot,1) : 0.0];
        }

        // Duplicates (qty>1)
    $dupStmt = $db->prepare('SELECT c.card_id AS id, c.qty, k.name, k.set_code, k.rarity, k.color, k.card_type, k.image_url, COALESCE(k.price, 0) AS price FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 1 ORDER BY k.set_code, k.name');
        $dupStmt->execute([$userId]);
        $duplicates = $dupStmt->fetchAll();
        $duplicatesCount = 0;
        foreach ($duplicates as $d) { $duplicatesCount += max(0, ((int)$d['qty']) - 1); }

        // Missing
        $missCountStmt = $db->prepare('SELECT COUNT(*) FROM ' . self::t('cards_cache') . ' k LEFT JOIN ' . self::t('collections') . ' c ON c.card_id = k.id AND c.user_id = ? WHERE (c.qty IS NULL OR c.qty <= 0)');
        $missCountStmt->execute([$userId]);
        $missingCount = (int)$missCountStmt->fetchColumn();
    $missStmt = $db->prepare('SELECT k.id, k.name, k.set_code, k.rarity, k.color, k.card_type, k.image_url, COALESCE(k.price,0) AS price FROM ' . self::t('cards_cache') . ' k LEFT JOIN ' . self::t('collections') . ' c ON c.card_id = k.id AND c.user_id = :uid WHERE (c.qty IS NULL OR c.qty <= 0) ORDER BY k.set_code, k.id LIMIT :limit');
    $missStmt->bindValue(':uid', (int)$userId, \PDO::PARAM_INT);
    $missStmt->bindValue(':limit', max(1, $missingLimit), \PDO::PARAM_INT);
    $missStmt->execute();
        $missing = $missStmt->fetchAll();

        // Value estimate
        // Prefer explicit card price when present, otherwise rough mapping by rarity
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

        $valStmt = $db->prepare('SELECT c.qty, COALESCE(k.price, 0) AS price, COALESCE(k.rarity, "") AS rarity FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0');
        $valStmt->execute([$userId]);
        $ownedValue = 0.0;
        while ($row = $valStmt->fetch()) {
            $qty = (int)($row['qty'] ?? 0);
            $price = (float)($row['price'] ?? 0);
            if ($price <= 0) {
                $rar = strtolower((string)($row['rarity'] ?? ''));
                $price = (float)($priceMap[$rar] ?? 0.0);
            }
            $ownedValue += max(0, $qty) * max(0.0, $price);
        }

        $missValStmt = $db->query('SELECT COALESCE(SUM(CASE WHEN k.price IS NOT NULL AND k.price > 0 THEN k.price ELSE 
            CASE LOWER(COALESCE(k.rarity, ""))
                WHEN "common" THEN 0.10 WHEN "commune" THEN 0.10 
                WHEN "uncommon" THEN 0.25 WHEN "peu commune" THEN 0.25 
                WHEN "rare" THEN 1.00 
                WHEN "epic" THEN 3.50 WHEN "epique" THEN 3.50 
                WHEN "legendary" THEN 8.00 WHEN "legendaire" THEN 8.00 
                WHEN "overnumbered" THEN 15.00 
                ELSE 0.00 END END),0) AS v FROM ' . self::t('cards_cache') . ' k LEFT JOIN ' . self::t('collections') . ' c ON c.card_id = k.id AND c.user_id = ' . (int)$userId . ' WHERE (c.qty IS NULL OR c.qty <= 0)');
        $missingValue = (float)($missValStmt->fetch()['v'] ?? 0);

        // Progress by set detailed (rarity breakdown)
        $pbs = [];
        $rows = $db->query('SELECT set_code, COALESCE(rarity, "") AS rarity, COUNT(*) AS total FROM ' . self::t('cards_cache') . ' GROUP BY set_code, rarity')->fetchAll();
        $ownedRows = $db->prepare('SELECT k.set_code AS set_code, COALESCE(k.rarity, "") AS rarity, COUNT(*) AS owned FROM ' . self::t('collections') . ' c JOIN ' . self::t('cards_cache') . ' k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.set_code, k.rarity');
        $ownedRows->execute([$userId]);
        $map = [];
        foreach ($rows as $r) {
            $set = (string)($r['set_code'] ?? ''); $rar = (string)($r['rarity'] ?? '');
            if (!isset($map[$set])) $map[$set] = [];
            $map[$set][$rar] = ['total' => (int)$r['total'], 'owned' => 0];
        }
        foreach ($ownedRows->fetchAll() as $r) {
            $set = (string)($r['set_code'] ?? ''); $rar = (string)($r['rarity'] ?? '');
            if (!isset($map[$set])) $map[$set] = [];
            if (!isset($map[$set][$rar])) $map[$set][$rar] = ['total' => 0, 'owned' => 0];
            $map[$set][$rar]['owned'] = (int)$r['owned'];
        }
        foreach ($map as $set => $rarities) {
            $items = [];
            $own = 0; $tot = 0;
            foreach ($rarities as $rar => $vals) { $own += (int)$vals['owned']; $tot += (int)$vals['total']; $items[] = ['rarity' => $rar, 'owned' => (int)$vals['owned'], 'total' => (int)$vals['total']]; }
            $pbs[] = ['set' => $set, 'owned' => $own, 'total' => $tot, 'percent' => $tot ? round($own*100/$tot,1) : 0.0, 'rarities' => $items];
        }
        // Sort by set code
        usort($pbs, function($a,$b){ return strcmp((string)$a['set'], (string)$b['set']); });

        return [
            'global' => ['owned' => $ownedUnique, 'total' => $totalCards, 'percent' => $totalCards ? round($ownedUnique*100/$totalCards, 1) : 0.0],
            'byRarity' => $byRarity,
            'bySet' => $bySet,
            'byColor' => $byColor,
            'byType' => $byType,
            'duplicates' => ['count' => $duplicatesCount, 'items' => $duplicates],
            'missing' => ['count' => $missingCount, 'limit' => $missingLimit, 'items' => $missing],
            'value' => ['owned' => round($ownedValue, 2), 'missing' => round($missingValue, 2), 'currency' => 'EUR'],
            'progressBySet' => $pbs,
            'updated_at' => time(),
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

    // -------- Share collection (public read-only) --------

    public static function userShareInfo(int $userId): array
    {
        $db = self::pdo();
        $stmt = $db->prepare('SELECT share_enabled, share_token FROM ' . self::t('users') . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
        $enabled = (int)($row['share_enabled'] ?? 0) === 1;
        $token = $enabled ? (string)($row['share_token'] ?? '') : '';
        if ($token === '') $enabled = false; // safety: no token means disabled
        return [ 'enabled' => $enabled, 'token' => $enabled ? $token : null ];
    }

    public static function userShareSet(int $userId, int $enabled): array
    {
        $db = self::pdo();
        $enabledFlag = $enabled ? 1 : 0;
        $token = null;
        if ($enabledFlag === 1) {
            // Ensure existing token or generate new
            $stmt = $db->prepare('SELECT share_token FROM ' . self::t('users') . ' WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $cur = $stmt->fetch();
            $token = isset($cur['share_token']) && (string)$cur['share_token'] !== '' ? (string)$cur['share_token'] : null;
            if ($token === null) {
                $token = bin2hex(random_bytes(12));
            }
        }
        $stmt2 = $db->prepare('UPDATE ' . self::t('users') . ' SET share_enabled = ?, share_token = ? WHERE id = ?');
        $stmt2->execute([$enabledFlag, $token, $userId]);
        return [ 'enabled' => $enabledFlag === 1, 'token' => $enabledFlag === 1 ? $token : null ];
    }

    public static function userIdByShareToken(string $token): ?int
    {
        $t = trim($token);
        if ($t === '') return null;
        $db = self::pdo();
        $stmt = $db->prepare('SELECT id FROM ' . self::t('users') . ' WHERE share_enabled = 1 AND share_token = ? LIMIT 1');
        $stmt->execute([$t]);
        $row = $stmt->fetch();
        return $row && isset($row['id']) ? (int)$row['id'] : null;
    }
}
