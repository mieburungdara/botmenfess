<?php
/**
 * Publish Draft API Endpoint
 * Updates a submission status from pending to approved
 * 
 * POST parameters:
 * - submission_id: ID of the submission to publish
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionId = (int)($input['submission_id'] ?? 0);
    
    if (!$submissionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'submission_id is required'
        ]);
        exit;
    }
    
    // Check if submission exists and is pending
    $stmt = $pdo->prepare('SELECT id, status FROM submissions WHERE id = ?');
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
    
    if (!$submission) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Submission not found'
        ]);
        exit;
    }
    
    if ($submission['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Submission cannot be published. Current status: ' . $submission['status']
        ]);
        exit;
    }
    
    // Update submission status to approved
    $stmt = $pdo->prepare('UPDATE submissions SET status = ?, reviewed_at = NOW() WHERE id = ?');
    $stmt->execute(['approved', $submissionId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission berhasil dipublish',
        'submission_id' => $submissionId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
        // Don't expose internal error details in production
    ]);
}
