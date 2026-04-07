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
define('DISCUSSION_GROUP_ID',$_ENV['DISCUSSION_GROUP_ID'] ?? '');
define('USE_WEBHOOK',filter_var($_ENV['USE_WEBHOOK'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('WEBHOOK_URL',$_ENV['WEBHOOK_URL'] ?? '');
$adminIdsRaw = $_ENV['ADMIN_TELEGRAM_IDS'] ?? '';
define('ADMIN_TELEGRAM_IDS', $adminIdsRaw !== '' ? array_map('trim',explode(',', $adminIdsRaw)) : []);
define('DEFAULT_TIER_LIMIT', 3);
define('POST_INTERVAL_MINUTES',(int)($_ENV['POST_INTERVAL_MINUTES'] ?? 1));
define('LOG_FILE',__DIR__ . '/logs/bot.log');

// Comment & Leaderboard Settings
define('MIN_COMMENT_LENGTH', 10);
define('COMMENT_RATE_LIMIT_SECONDS', 12); // Min 5 comments per minute
define('COMMENT_DUPLICATE_CHECK_HOURS', 1);
define('LEADERBOARD_CACHE_HOURS', 24);
define('LEADERBOARD_DEFAULT_LIMIT', 50);

// Badge Definitions
define('BADGES', [
    'weekly_champion' => ['name' => 'Weekly Champion', 'icon' => '🏅', 'description' => 'Rank #1 mingguan'],
    'monthly_legend' => ['name' => 'Monthly Legend', 'icon' => '🌟', 'description' => 'Top 3 bulanan'],
    'alltime_king' => ['name' => 'All-Time King', 'icon' => '👑', 'description' => 'Rank #1 all-time'],
    'rising_star' => ['name' => 'Rising Star', 'icon' => '⚡', 'description' => 'Velocity tertinggi minggu ini'],
    'active_commenter' => ['name' => 'Active Commenter', 'icon' => '💬', 'description' => '100+ komentar'],
    'newcomer' => ['name' => 'Newcomer', 'icon' => '🌱', 'description' => 'Komentar pertama'],
    'early_bird' => ['name' => 'Early Bird', 'icon' => '🐦', 'description' => '50%+ komentar jam 06-09'],
    'night_owl' => ['name' => 'Night Owl', 'icon' => '🦉', 'description' => '50%+ komentar jam 23-02'],
    'streak_master' => ['name' => 'Streak Master', 'icon' => '🔥', 'description' => '7+ hari berturut-turut'],
    'conversation_starter' => ['name' => 'Conversation Starter', 'icon' => '💭', 'description' => 'Submission dengan 20+ komentar'],
]);

// Early Bird hours (06-09)
define('EARLY_BIRD_START', 6);
define('EARLY_BIRD_END', 9);

// Night Owl hours (23-02)
define('NIGHT_OWL_START', 23);
define('NIGHT_OWL_END', 2);

// Auto Title Definitions (dynamic, can be lost if criteria not met)
define('AUTO_TITLES', [
    'night_warrior' => ['name' => 'Night Warrior', 'icon' => '🦉', 'description' => 'Aktif di malam hari (50%+ komentar jam 23:00-04:00)'],
    'early_bird' => ['name' => 'Early Bird', 'icon' => '🐦', 'description' => 'Aktif di pagi hari (50%+ komentar jam 05:00-08:00)'],
    'philosopher' => ['name' => 'Philosopher', 'icon' => '📚', 'description' => 'Rata-rata panjang komentar > 200 karakter'],
    'social_butterfly' => ['name' => 'Social Butterfly', 'icon' => '🦋', 'description' => 'Komentar di 10+ thread berbeda'],
    'speed_demon' => ['name' => 'Speed Demon', 'icon' => '⚡', 'description' => '5+ komentar dalam 1 jam'],
    'hot_streak' => ['name' => 'Hot Streak', 'icon' => '🔥', 'description' => 'Aktif 14+ hari berturut-turut'],
    'deep_thinker' => ['name' => 'Deep Thinker', 'icon' => '💎', 'description' => '50+ komentar dengan rata-rata panjang > 150 karakter'],
    'trending' => ['name' => 'Trending', 'icon' => '🌟', 'description' => 'Velocity komentar tertinggi minggu ini'],
    'focused' => ['name' => 'Focused', 'icon' => '🎯', 'description' => '80%+ komentar di 3 thread yang sama'],
    'analyst' => ['name' => 'Analyst', 'icon' => '📊', 'description' => 'Komentar di 25+ thread berbeda'],
]);

// Web App Configuration
// Use WEBAPP_URL if set, otherwise derive from WEBHOOK_URL
$webappBaseUrl = $_ENV['WEBAPP_URL'] ?? (rtrim($_ENV['WEBHOOK_URL'] ?? '', '/') . '/public/webapp');
define('WEBAPP_URL', $webappBaseUrl);
