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
            json_ok(['user' => ['id' => $user['id'], 'email' => $user['email']]]);

        case 'logout':
            Auth::logout();
            json_ok(['bye' => true]);

        case 'me':
            $u = Auth::user();
            if (!$u) json_error('Non connecté', 401);
            json_ok(['user' => ['id' => $u['id'], 'email' => $u['email']]]);

        case 'cards.list':
            // Filters: q (search), rarity, set, page, pageSize
            $q = trim($_GET['q'] ?? '');
            $rarity = trim($_GET['rarity'] ?? '');
            $set = trim($_GET['set'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 30)));
            $data = Database::cardsList($q, $rarity, $set, $page, $pageSize);
            json_ok($data);

        case 'cards.refresh':
            // Admin or owner-only in future; for now open
            $count = Database::syncCardsFromApi();
            json_ok(['synced' => $count]);

        case 'cards.detail':
            $id = trim($_GET['id'] ?? '');
            if ($id === '') json_error('Paramètre id manquant');
            $card = Database::cardDetail($id);
            if (!$card) json_error('Carte introuvable', 404);
            json_ok($card);

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
