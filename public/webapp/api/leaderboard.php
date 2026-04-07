<?php
/**
 * Leaderboard API Endpoint
 * Returns cached leaderboard data + personal rank
 * 
 * Query parameters:
 * - timeframe: alltime (default), weekly, monthly
 * - telegram_id: for personal rank (optional, from Telegram Web App init)
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../helpers.php';

// Rate limiting (simple file-based)
$rateLimitFile = __DIR__ . '/../cache/rate_limit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.txt';
if (!is_dir(dirname($rateLimitFile))) {
    mkdir(dirname($rateLimitFile), 0755, true);
}

// Simple rate limit: 30 requests per minute
if (file_exists($rateLimitFile)) {
    $lastRequest = (int)file_get_contents($rateLimitFile);
    if (time() - $lastRequest < 2) { // Minimum 2 seconds between requests
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait.']);
        exit;
    }
}
file_put_contents($rateLimitFile, time());

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get parameters
    $timeframe = $_GET['timeframe'] ?? 'alltime';
    $telegramId = $_GET['telegram_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? LEADERBOARD_DEFAULT_LIMIT);
    // Sanitize limit: min 1, max 100
    $limit = min(max($limit, 1), 100);
    
    // Validate timeframe
    if (!in_array($timeframe, ['alltime', 'weekly', 'monthly'])) {
        $timeframe = 'alltime';
    }
    
    // Get leaderboard from cache
    $leaderboardData = getLeaderboard($pdo, $timeframe, $limit);
    
    // Get personal rank if telegram_id provided
    $personalRank = null;
    if ($telegramId) {
        $personalRank = getPersonalRank($pdo, $telegramId, $timeframe);
    }
    
    // Format response
    $response = [
        'success' => true,
        'timeframe' => $timeframe,
        'leaderboard' => $leaderboardData['leaderboard'] ?? [],
        'stats' => $leaderboardData['stats'] ?? [],
        'personal_rank' => $personalRank,
        'generated_at' => $leaderboardData['generated_at'] ?? date('Y-m-d H:i:s'),
        'cached' => $leaderboardData['cached'] ?? false
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}