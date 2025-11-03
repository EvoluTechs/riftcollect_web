<?php
namespace RiftCollect;

final class Config
{
    private static bool $initialized = false;

    public static string $API_BASE_URL;
    public static ?string $API_KEY;

    public static string $DB_DRIVER; // 'sqlite' | 'mysql'
    public static string $DB_DSN;
    public static ?string $DB_USER;
    public static ?string $DB_PASS;

    public static string $STORAGE_PATH;
    public static array $CDN_IMAGES = [];

    public static function init(): void
    {
        if (self::$initialized) return;

        self::$STORAGE_PATH = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
        if (!is_dir(self::$STORAGE_PATH)) {
            @mkdir(self::$STORAGE_PATH, 0775, true);
        }

        // External API configuration (placeholder values)
        self::$API_BASE_URL = getenv('RIFT_API_BASE') ?: 'https://api.riftbound.example.com/v1';
        self::$API_KEY = getenv('RIFT_API_KEY') ?: null; // put your key in OVH env var if needed

        // Database config: prefer SQLite by default for easy OVH deployment
        $driver = strtolower(getenv('RC_DB_DRIVER') ?: 'sqlite');
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            $driver = 'sqlite';
        }
        self::$DB_DRIVER = $driver;

        if ($driver === 'sqlite') {
            $dbFile = getenv('RC_SQLITE_FILE') ?: (self::$STORAGE_PATH . '/riftcollect.sqlite');
            self::$DB_DSN = 'sqlite:' . $dbFile;
            self::$DB_USER = null;
            self::$DB_PASS = null;
        } else {
            $host = getenv('RC_MYSQL_HOST') ?: 'localhost';
            $dbname = getenv('RC_MYSQL_DB') ?: 'riftcollect';
            $charset = getenv('RC_MYSQL_CHARSET') ?: 'utf8mb4';
            self::$DB_DSN = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            self::$DB_USER = getenv('RC_MYSQL_USER') ?: 'root';
            self::$DB_PASS = getenv('RC_MYSQL_PASS') ?: '';
        }

        self::$initialized = true;

        // Optional public CDN images for landing (comma-separated list in env RC_CDN_IMAGES)
        $cdnCsv = getenv('RC_CDN_IMAGES');
        if ($cdnCsv !== false && $cdnCsv !== null && $cdnCsv !== '') {
            $list = array_map('trim', explode(',', $cdnCsv));
        } else {
            // Default: single sample image provided by user (can be removed via env)
            $list = [
                'https://cdn.rgpub.io/public/live/map/riftbound/latest/OGN/cards/OGN-310/full-desktop.jpg',
            ];
        }
        // Keep only http/https URLs
        self::$CDN_IMAGES = array_values(array_filter($list, function ($u) {
            return is_string($u) && preg_match('#^https?://#i', $u);
        }));
    }
}
