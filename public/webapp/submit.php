<?php
/**
 * Submit endpoint for webapp
 * Handles submission from Telegram Web App with proper validation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers.php';

try {
    // Database connection using PDO (standardized across all endpoints)
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message']) || !isset($input['initData'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request. Message and initData are required.'
        ]);
        exit;
    }
    
    $message = trim($input['message']);
    $initData = $input['initData'];
    
    // Validate Telegram init data for authentication
    if (!validateTelegramInitData($initData)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Telegram authentication'
        ]);
        exit;
    }
    
    // Parse init data to get user info
    parse_str($initData, $data);
    $userData = json_decode($data['user'] ?? '{}', true);
    
    // Validate message
    if (empty($message) || strlen($message) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Pesan terlalu pendek, minimal 2 karakter'
        ]);
        exit;
    }
    
    if (strlen($message) > 1000) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Pesan terlalu panjang, maksimal 1000 karakter'
        ]);
        exit;
    }
    
    $userId = $userData['id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to get user information'
        ]);
        exit;
    }
    
    // Get username if available
    $username = $userData['username'] ?? null;
    $firstName = $userData['first_name'] ?? null;
    $lastName = $userData['last_name'] ?? null;
    
    // Check if user is shadow banned
    if (isUserShadowBanned($pdo, $userId)) {
        // Silently accept but don't actually store (shadow ban behavior)
        echo json_encode([
            'success' => true,
            'message' => 'Pesan berhasil dikirim'
        ]);
        exit;
    }
    
    // Use getOrCreateUser helper to handle user creation/updates
    $dbUserId = getOrCreateUser($pdo, $userId, $username, $firstName, $lastName);
    
    // Update submission count
    $stmt = $pdo->prepare('UPDATE users SET submission_count = submission_count + 1 WHERE id = ?');
    $stmt->execute([$dbUserId]);
    
    // Store submission
    $stmt = $pdo->prepare("INSERT INTO submissions (message_text, submitted_at) VALUES (?, NOW())");
    $stmt->execute([$message]);
    $submissionId = (int)$pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'submissionId' => $submissionId,
        'message' => 'Pesan berhasil dikirim'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}

/**
 * Validate Telegram Web App init data
 * @param string $initData Raw init data string
 * @return bool
 */
function validateTelegramInitData($initData) {
    if (empty($initData)) {
        return false;
    }
    
    // For development/testing, we can skip validation
    // In production, always validate
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
        return false;
    }
    
    try {
        parse_str($initData, $data);
        
        if (!isset($data['hash'])) {
            return false;
        }
        
        $receivedHash = $data['hash'];
        unset($data['hash']);
        
        // Sort parameters alphabetically
        ksort($data);
        
        // Build check string
        $checkString = '';
        foreach ($data as $key => $value) {
            if ($checkString !== '') {
                $checkString .= "\n";
            }
            $checkString .= $key . '=' . $value;
        }
        
        // Calculate secret key
        $secretKey = hash_hmac('sha256', TELEGRAM_BOT_TOKEN, 'WebAppData', true);
        
        // Calculate hash
        $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);
        
        return hash_equals($calculatedHash, $receivedHash);
    } catch (Exception $e) {
        return false;
    }
}
