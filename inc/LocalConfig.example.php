<?php
namespace RiftCollect;

// Local overrides for secrets and environment-specific settings.
// Copy this file to inc/LocalConfig.php (DO NOT COMMIT) and fill in your values.
// This file is optional; environment variables are preferred in production.
final class LocalConfig
{
    public static function apply(): void
    {
        // Database (MySQL)
        // Config::$DB_DRIVER = 'mysql';
        // Config::$DB_DSN = 'mysql:host=your-host;dbname=your-db;charset=utf8mb4';
        // Config::$DB_USER = 'your-user';
        // Config::$DB_PASS = 'your-pass';

        // OpenAI
        // Config::$LLM_ENABLED = 1; // set 0 to disable IA features globally
        // Config::$OPENAI_API_KEY = 'sk-...';
        // Config::$LLM_MODEL = 'gpt-4o-mini';
        // Config::$LLM_BASE_URL = 'https://api.openai.com/v1';

        // Admins
        // Config::$ADMIN_EMAILS = ['admin@example.com'];
    }
}
