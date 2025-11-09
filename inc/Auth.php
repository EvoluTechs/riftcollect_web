<?php
namespace RiftCollect;

use RuntimeException; use PDO;

final class Auth
{
    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookieParams['path'] ?? '/',
                'domain' => $cookieParams['domain'] ?? '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function register(string $email, string $password): int
    {
        $db = Database::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = time();
    $stmt = $db->prepare('INSERT INTO ' . Database::t('users') . ' (email, password_hash, created_at) VALUES (?,?,?)');
        try {
            $stmt->execute([$email, $hash, $now]);
        } catch (\PDOException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new RuntimeException('Email déjà utilisé');
            }
            throw $e;
        }
        $id = (int)$db->lastInsertId();
        $_SESSION['uid'] = $id;
        return $id;
    }

    public static function login(string $email, string $password): ?array
    {
        $db = Database::pdo();
    $stmt = $db->prepare('SELECT id, email, password_hash FROM ' . Database::t('users') . ' WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) return null;
        if (!password_verify($password, $u['password_hash'])) return null;
        $_SESSION['uid'] = (int)$u['id'];
        return ['id' => (int)$u['id'], 'email' => $u['email']];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        $uid = (int)($_SESSION['uid'] ?? 0);
        if ($uid <= 0) return null;
        $db = Database::pdo();
        $stmt = $db->prepare('SELECT id, email FROM ' . Database::t('users') . ' WHERE id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $u ?: null;
    }

    public static function requireUser(): array
    {
        $u = self::user();
        if (!$u) throw new RuntimeException('Non connecté');
        return $u;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        if (!$u) return false;
        $email = strtolower((string)($u['email'] ?? ''));
        $admins = \RiftCollect\Config::$ADMIN_EMAILS ?? [];
        return in_array($email, $admins, true);
    }

    public static function requireAdmin(): array
    {
        $u = self::requireUser();
        if (!self::isAdmin()) throw new RuntimeException('Accès administrateur requis');
        return $u;
    }
}
