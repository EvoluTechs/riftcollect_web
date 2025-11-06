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
    public static string $DB_TABLE_PREFIX; // e.g. 'riftcollect_'

    public static string $STORAGE_PATH;
    public static array $CDN_IMAGES = [];
    public static array $ADMIN_EMAILS = [];

    // LLM (ChatGPT/OpenAI) configuration
    public static int $LLM_ENABLED; // 0|1
    public static string $LLM_MODEL; // e.g. gpt-4o-mini
    public static int $LLM_MAX_CALLS; // per run budget
    public static string $LLM_BASE_URL; // default https://api.openai.com/v1
    public static ?string $OPENAI_API_KEY; // from env OPENAI_API_KEY

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

        // Database config: prefer MySQL by default (can override with RC_DB_DRIVER)
        $driver = strtolower(getenv('RC_DB_DRIVER') ?: 'mysql');
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            $driver = 'sqlite';
        }
        self::$DB_DRIVER = $driver;

        // Table prefix (used everywhere)
        self::$DB_TABLE_PREFIX = getenv('RC_DB_PREFIX') ?: 'riftcollect_';

        if ($driver === 'sqlite') {
            $dbFile = getenv('RC_SQLITE_FILE') ?: (self::$STORAGE_PATH . '/riftcollect.sqlite');
            self::$DB_DSN = 'sqlite:' . $dbFile;
            self::$DB_USER = null;
            self::$DB_PASS = null;
        } else {
            // Defaults filled with provided OVH credentials, can be overridden by env
            $host = getenv('RC_MYSQL_HOST') ?: 'yannickrpubase.mysql.db';
            $dbname = getenv('RC_MYSQL_DB') ?: 'yannickrpubase';
            $charset = getenv('RC_MYSQL_CHARSET') ?: 'utf8mb4';
            self::$DB_DSN = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            self::$DB_USER = getenv('RC_MYSQL_USER') ?: null;
            self::$DB_PASS = getenv('RC_MYSQL_PASS') ?: null;
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

        // LLM defaults from environment
    // Enable LLM by default; can be disabled by setting RC_LLM_ENABLED=0 in the environment
    self::$LLM_ENABLED   = (int)(getenv('RC_LLM_ENABLED') ?: '1');
        self::$LLM_MODEL     = getenv('RC_LLM_MODEL') ?: 'gpt-4o-mini';
        self::$LLM_MAX_CALLS = (int)(getenv('RC_LLM_MAX_CALLS') ?: '10000');
    self::$LLM_BASE_URL  = getenv('RC_LLM_BASE_URL') ?: 'https://api.openai.com/v1';
    self::$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: null; // DO NOT hardcode secrets; set env on server

        // Admin emails (comma-separated list) — default to the requested admin
        $adminCsv = getenv('RC_ADMIN_EMAILS');
        if ($adminCsv !== false && $adminCsv !== null && $adminCsv !== '') {
            $admins = array_map('trim', explode(',', $adminCsv));
        } else {
            $admins = ['yan.ruault@gmail.com'];
        }
        // Normalize to lowercase unique
        $admins = array_values(array_unique(array_map(function ($e) {
            return strtolower((string)$e);
        }, array_filter($admins, fn($e) => is_string($e) && $e !== ''))));
        self::$ADMIN_EMAILS = $admins;
    }
}
