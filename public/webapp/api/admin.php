<?php
/**
 * Admin API Endpoint
 * Handles admin actions: stats, cache rebuild, shadow ban management
 * 
 * POST parameters:
 * - action: rebuild_cache, shadow_ban, unshadow_ban, get_stats
 * - telegram_id: for ban/unban actions
 * 
 * Note: In production, add proper admin authentication
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../helpers.php';

// Simple admin check - in production, use proper authentication
function isAdmin($telegramId) {
    return $telegramId !== null && in_array($telegramId, ADMIN_TELEGRAM_IDS, true);
}

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get request method and data
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get admin stats
        $telegramId = $_GET['telegram_id'] ?? null;
        
        if (!isAdmin($telegramId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $stats = getAdminStats($pdo);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }
    
    // POST requests
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $telegramId = $input['telegram_id'] ?? null;
    $adminId = $input['admin_id'] ?? null;
    
    if (!isAdmin($adminId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    switch ($action) {
        case 'rebuild_cache':
            $results = rebuildAllCaches($pdo);
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'shadow_ban':
            if (!$telegramId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'telegram_id required']);
                exit;
            }
            shadowBanUser($pdo, $telegramId, 'admin_' . $adminId);
            echo json_encode(['success' => true, 'message' => 'User shadow banned']);
            break;
            
        case 'unshadow_ban':
            if (!$telegramId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'telegram_id required']);
                exit;
            }
            unshadowBanUser($pdo, $telegramId);
            echo json_encode(['success' => true, 'message' => 'User unshadow banned']);
            break;
            
        case 'get_stats':
            $stats = getAdminStats($pdo);
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}