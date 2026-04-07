<?php
/**
 * Stats API Endpoint
 * Returns submission statistics for the dashboard
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
    // Database connection using PDO
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Total submissions
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM submissions');
    $total = (int)$stmt->fetch()['count'];
    
    // Pending submissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
    $pending = (int)$stmt->fetch()['count'];
    
    // Approved submissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'approved'");
    $approved = (int)$stmt->fetch()['count'];
    
    // Rejected submissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'rejected'");
    $rejected = (int)$stmt->fetch()['count'];
    
    // Today's submissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE DATE(submitted_at) = CURDATE()");
    $today = (int)$stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'pending' => $pending,
        'approved' => $approved,
        'rejected' => $rejected,
        'today' => $today
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
        // Don't expose internal error details in production
    ]);
}
