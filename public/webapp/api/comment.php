<?php
/**
 * Comment API Endpoint
 * Handles comment submission from web app (optional - comments are mainly from discussion group)
 * 
 * POST parameters:
 * - telegram_id: user's telegram ID
 * - submission_id: the submission to comment on
 * - text: comment text
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $telegramId = $input['telegram_id'] ?? null;
    $submissionId = (int)($input['submission_id'] ?? 0);
    $text = $input['text'] ?? '';
    
    if (!$telegramId || !$submissionId || empty(trim($text))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    // Validate comment
    $errors = validateComment($pdo, $telegramId, $text);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        exit;
    }
    
    // Check if submission exists
    $stmt = $pdo->prepare('SELECT id FROM submissions WHERE id = ?');
    $stmt->execute([$submissionId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Submission not found']);
        exit;
    }
    
    // Save comment
    $commentId = saveComment($pdo, $telegramId, $submissionId, $text);
    
    if ($commentId) {
        echo json_encode([
            'success' => true,
            'comment_id' => $commentId,
            'message' => 'Komentar berhasil disimpan'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save comment']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}