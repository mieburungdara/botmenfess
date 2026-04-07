<?php

/**
 * Configuration - loads environment variables
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

define('DB_HOST',$_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME',$_ENV['DB_NAME'] ?? 'menfess_bot');
define('DB_USER',$_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASS',$_ENV['DB_PASSWORD'] ?? '');
define('TELEGRAM_BOT_TOKEN',$_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TARGET_CHANNEL_ID',$_ENV['TARGET_CHANNEL_ID'] ?? '');
define('USE_WEBHOOK',filter_var($_ENV['USE_WEBHOOK'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('WEBHOOK_URL',$_ENV['WEBHOOK_URL'] ?? '');
define('ADMIN_TELEGRAM_IDS',array_map('trim',explode(',',$_ENV['ADMIN_TELEGRAM_IDS'] ?? '')));
define('DEFAULT_TIER_LIMIT', 3);
define('POST_INTERVAL_MINUTES',(int)($_ENV['POST_INTERVAL_MINUTES'] ?? 1));
define('LOG_FILE',__DIR__ . '/logs/bot.log');