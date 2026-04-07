<?php
/**
 * Get Draft API Endpoint
 * Returns recent pending submissions that can be used as drafts
 * 
 * Query parameters:
 * - limit: number of drafts to return (default 10, max 50)
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
    // Database connection using PDO
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $limit = min(max((int)($_GET['limit'] ?? 10), 1), 50);
    
    // Get recent pending submissions
    $stmt = $pdo->prepare('
        SELECT id, message_text, submitted_at, status
        FROM submissions 
        WHERE status = ?
        ORDER BY submitted_at DESC
        LIMIT ?
    ');
    $stmt->execute(['pending', $limit]);
    $drafts = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'drafts' => $drafts,
        'count' => count($drafts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
        // Don't expose internal error details in production
    ]);
}
