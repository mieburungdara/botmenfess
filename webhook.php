<?php
require __DIR__ . '/src/Bot.php';

// Handle incoming webhook requests
$bot = new MenfessBot();

// Get the request body
$input = file_get_contents("php://input");
$update = json_decode($input, true);

// Process the update if we received one
if (!empty($update)) {
    try {
        // Convert array to Update object (simplified approach)
        // In a real implementation, you'd properly convert this to an Update object
        $bot->logger->info("Received webhook update");
        
        // For simplicity in this example, we'll process the update directly
        // A production implementation would properly handle the Update object
        if (isset($update['message']) && isset($update['message']['chat']['type']) && $update['message']['chat']['type'] === 'private') {
            $bot->handlePrivateMessageFromArray($update['message']);
        } elseif (isset($update['callback_query'])) {
            $bot->handleCallbackQueryFromArray($update['callback_query']);
        }
        
        // Process auto-approval queue
        $bot->processAutoApprovalQueue();
        
        // Return success response
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } catch (\Exception $e) {
        $bot->logger->error('Error processing webhook: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    // No update data, return bad request
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No update data received']);
}