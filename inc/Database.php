<?php
namespace RiftCollect;

use PDO; use PDOException; use RuntimeException; use Exception;

final class Database
{
    private static ?PDO $pdo = null;

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
                self::$pdo = new PDO(Config::$DB_DSN, Config::$DB_USER, Config::$DB_PASS, $opts);
            }
        } catch (PDOException $e) {
            throw new RuntimeException('DB connection failed: ' . $e->getMessage());
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
        // users
        $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');

        // cards cache
        $db->exec('CREATE TABLE IF NOT EXISTS cards_cache (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            rarity TEXT,
            set_code TEXT,
            image_url TEXT,
            data_json TEXT,
            updated_at INTEGER NOT NULL
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_cards_name ON cards_cache(name)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_cards_set ON cards_cache(set_code)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_cards_rarity ON cards_cache(rarity)');

        // collection
        $db->exec('CREATE TABLE IF NOT EXISTS collections (
            user_id INTEGER NOT NULL,
            card_id TEXT NOT NULL,
            qty INTEGER NOT NULL,
            PRIMARY KEY (user_id, card_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        // expansions
        $db->exec('CREATE TABLE IF NOT EXISTS expansions (
            code TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            released_at INTEGER,
            data_json TEXT,
            updated_at INTEGER NOT NULL
        )');

        // subscriptions (notifications opt-in)
        $db->exec('CREATE TABLE IF NOT EXISTS subscriptions (
            user_id INTEGER PRIMARY KEY,
            enabled INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        // notifications queue (optional future use)
        $db->exec('CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            sent_at INTEGER NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
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

    public static function syncCardsFromApi(): int
    {
        $db = self::pdo();
        // NOTE: Placeholder endpoint. Adjust in Config to real Riftbound API.
        $url = rtrim(Config::$API_BASE_URL, '/') . '/cards?locale=fr-FR';
        $data = self::http_get_json($url);
        $cards = $data['cards'] ?? $data ?? [];
        if (!is_array($cards)) $cards = [];
        $now = time();
        $insert = $db->prepare('REPLACE INTO cards_cache (id,name,rarity,set_code,image_url,data_json,updated_at) VALUES (?,?,?,?,?,?,?)');
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

    public static function cardsList(string $q, string $rarity, string $set, int $page, int $pageSize): array
    {
        $db = self::pdo();
        $where = [];
        $params = [];
        if ($q !== '') { $where[] = 'name LIKE :q'; $params[':q'] = '%' . $q . '%'; }
        if ($rarity !== '') { $where[] = 'rarity = :r'; $params[':r'] = $rarity; }
        if ($set !== '') { $where[] = 'set_code = :s'; $params[':s'] = $set; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $pageSize;
        $total = (int)$db->prepare("SELECT COUNT(*) FROM cards_cache $whereSql")->execute($params) ?: 0;
        $stmt = $db->prepare("SELECT id,name,rarity,set_code,image_url FROM cards_cache $whereSql ORDER BY name LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        // If empty and no filters, try initial sync
        if ($total === 0 && $q === '' && $rarity === '' && $set === '') {
            self::syncCardsFromApi();
            return self::cardsList($q, $rarity, $set, $page, $pageSize);
        }
        // Fetch total again properly
        $stmt2 = $db->prepare("SELECT COUNT(*) AS c FROM cards_cache $whereSql");
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

    public static function cardDetail(string $id): ?array
    {
        $db = self::pdo();
        $stmt = $db->prepare('SELECT * FROM cards_cache WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) return $row;
        // try fetch from API
        $url = rtrim(Config::$API_BASE_URL, '/') . '/cards/' . rawurlencode($id) . '?locale=fr-FR';
        try {
            $data = self::http_get_json($url);
        } catch (Exception $e) {
            return null;
        }
        if (!$data) return null;
        $c = $data['card'] ?? $data;
        $now = time();
        $id = (string)($c['id'] ?? $c['card_id'] ?? $id);
        $name = (string)($c['name'] ?? '');
        $rarity = (string)($c['rarity'] ?? '');
        $set = (string)($c['set'] ?? $c['set_code'] ?? '');
        $image = (string)($c['image'] ?? $c['image_url'] ?? '');
        $ins = $db->prepare('REPLACE INTO cards_cache (id,name,rarity,set_code,image_url,data_json,updated_at) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$id, $name, $rarity, $set, $image, json_encode($c, JSON_UNESCAPED_UNICODE), $now]);
        $stmt = $db->prepare('SELECT * FROM cards_cache WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // -------- Collection --------

    public static function collectionGet(int $userId): array
    {
        $db = self::pdo();
        $stmt = $db->prepare('SELECT c.card_id, c.qty, k.name, k.rarity, k.set_code, k.image_url FROM collections c LEFT JOIN cards_cache k ON k.id = c.card_id WHERE c.user_id = ? ORDER BY k.name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function collectionSet(int $userId, string $cardId, int $qty): void
    {
        $db = self::pdo();
        if ($qty <= 0) {
            $del = $db->prepare('DELETE FROM collections WHERE user_id = ? AND card_id = ?');
            $del->execute([$userId, $cardId]);
            return;
        }
        $stmt = $db->prepare('REPLACE INTO collections (user_id,card_id,qty) VALUES (?,?,?)');
        $stmt->execute([$userId, $cardId, $qty]);
    }

    public static function collectionBulkSet(int $userId, array $items): int
    {
        $db = self::pdo();
        $db->beginTransaction();
        try {
            $count = 0;
            $ins = $db->prepare('REPLACE INTO collections (user_id,card_id,qty) VALUES (?,?,?)');
            $del = $db->prepare('DELETE FROM collections WHERE user_id = ? AND card_id = ?');
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
        $rar = $db->query('SELECT rarity, COUNT(*) AS total FROM cards_cache GROUP BY rarity')->fetchAll();
        $ownR = $db->prepare('SELECT k.rarity AS rarity, COUNT(*) AS owned FROM collections c JOIN cards_cache k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.rarity');
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
        $setTot = $db->query('SELECT set_code, COUNT(*) AS total FROM cards_cache GROUP BY set_code')->fetchAll();
        $setOwn = $db->prepare('SELECT k.set_code, COUNT(*) AS owned FROM collections c JOIN cards_cache k ON k.id = c.card_id WHERE c.user_id = ? AND c.qty > 0 GROUP BY k.set_code');
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
        $t = (int)$db->query('SELECT COUNT(*) FROM cards_cache')->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM collections WHERE user_id = ? AND qty > 0');
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
        $ins = $db->prepare('REPLACE INTO expansions (code,name,released_at,data_json,updated_at) VALUES (?,?,?,?,?)');
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
        $stmt = $db->query('SELECT code, name, released_at, updated_at FROM expansions ORDER BY released_at DESC NULLS LAST');
        $rows = $stmt->fetchAll();
        if (!$rows) {
            self::syncExpansionsFromApi();
            $stmt = $db->query('SELECT code, name, released_at, updated_at FROM expansions ORDER BY released_at DESC');
            $rows = $stmt->fetchAll();
        }
        return $rows ?: [];
    }

    public static function subscriptionSet(int $userId, int $enabled): void
    {
        $db = self::pdo();
        $stmt = $db->prepare('REPLACE INTO subscriptions (user_id, enabled) VALUES (?,?)');
        $stmt->execute([$userId, $enabled ? 1 : 0]);
    }
}
