<?php
/**
 * Daily cron job to rebuild leaderboard cache
 * Run this script every day at 00:00 via cron
 * 
 * Cron example (Linux):
 * 0 0 * * * php /path/to/botmenfess/cron/rebuild_cache.php
 * 
 * Windows Task Scheduler:
 * Create a task that runs daily with: php C:\path\to\botmenfess\cron\rebuild_cache.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('menfess_cron');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/cron.log', Logger::INFO));

$logger->info('Starting daily leaderboard cache rebuild...');

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Rebuild all caches
    $results = rebuildAllCaches($pdo);
    
    $logger->info('Cache rebuild complete: ' . json_encode($results));
    
    // Also check and award badges for active users (exclude shadow banned)
    // Note: We use sendBadgeNotificationBeforeAward to send notification BEFORE awarding
    // because sendBadgeNotification checks if badge exists and returns early if it does
    $stmt = $pdo->query('SELECT DISTINCT u.telegram_id, u.id as user_id FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND u.is_shadow_banned = 0');
    $activeUsers = $stmt->fetchAll();
    
    $badgeCount = 0;
    foreach ($activeUsers as $user) {
        $eligibleBadges = checkBadgeEligibility($pdo, $user['user_id']);
        foreach ($eligibleBadges as $badgeKey) {
            // Send notification BEFORE awarding (so the badge check in sendBadgeNotification works)
            sendBadgeNotificationBeforeAward($pdo, $user['telegram_id'], $badgeKey);
            if (awardBadge($pdo, $user['user_id'], $badgeKey)) {
                $badgeCount++;
            }
        }
    }
    
    $logger->info("Badge check complete: {$badgeCount} new badges awarded");
    
    echo "Daily maintenance complete.\n";
    echo "Cache results: " . json_encode($results) . "\n";
    echo "Badges awarded: {$badgeCount}\n";
    
} catch (Exception $e) {
    $logger->error('Error in cron job: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}