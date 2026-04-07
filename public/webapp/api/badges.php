<?php
/**
 * Badges API Endpoint
 * Returns all badge definitions from config.php
 * This eliminates the need to duplicate badge definitions in JavaScript
 * 
 * GET request - no parameters required
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config.php';

try {
    // Return badge definitions from config.php
    $response = [
        'success' => true,
        'badges' => BADGES,
        'auto_titles' => defined('AUTO_TITLES') ? AUTO_TITLES : []
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