<?php
require __DIR__ . '/src/Bot.php';

$bot = new MenfessBot();

// Check if we should use webhook mode or getUpdates mode
if (isset($_ENV['USE_WEBHOOK']) && $_ENV['USE_WEBHOOK'] === 'true') {
    // Webhook mode - just set up the webhook and exit
    // The actual webhook processing will happen in webhook.php
    if ($bot->setWebhook()) {
        echo "Webhook set successfully. Bot is ready to receive updates via webhook.\n";
    } else {
        echo "Failed to set webhook. Check your WEBHOOK_URL environment variable.\n";
        exit(1);
    }
} else {
    // getUpdates mode - traditional polling
    $bot->start();
}