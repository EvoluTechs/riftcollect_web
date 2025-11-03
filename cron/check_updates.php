<?php
// Cron script to refresh expansions data and (optionally) queue notifications.
// On OVH, schedule this script via the Cron tasks to hit this URL or run via CLI.

declare(strict_types=1);

require_once __DIR__ . '/../inc/Config.php';
require_once __DIR__ . '/../inc/Database.php';

use RiftCollect\Config; use RiftCollect\Database;

Config::init();
Database::instance();

$countE = Database::syncExpansionsFromApi();
header('Content-Type: text/plain; charset=utf-8');
echo "Expansions synced: $countE\n";
