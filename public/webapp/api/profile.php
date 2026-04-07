<?php
/**
 * User Profile API Endpoint
 * Returns user profile data including stats, badges, comment history, and activity
 * 
 * Query parameters:
 * - telegram_id: required (from Telegram Web App init)
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../helpers.php';

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $telegramId = $_GET['telegram_id'] ?? null;
    
    if (!$telegramId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'telegram_id is required']);
        exit;
    }
    
    $profile = getUserProfile($pdo, $telegramId);
    
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Get personal rank (alltime for profile overview)
    $personalRank = getPersonalRank($pdo, $telegramId, 'alltime');
    $profile['personal_rank'] = $personalRank;
    
    echo json_encode(['success' => true, 'profile' => $profile]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}