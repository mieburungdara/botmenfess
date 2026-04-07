<?php
/**
 * MenfessBot - Main Bot Class
 * Fixed: Standardized to PDO, removed Doctrine DBAL dependency,
 * added proper error handling, and removed duplicate dotenv loading
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MenfessBot
{
    private $botApi;
    private $pdo;
    private $logger;
    private $targetChannelId;
    private $botToken;
    private $lastProcessedTime;
    private $postingInterval;
    private $webhookUrl;

    public function __construct()
    {
        // Load environment variables from config.php (single source of truth)
        require_once __DIR__ . '/../config.php';

        $this->botToken = TELEGRAM_BOT_TOKEN;
        $this->targetChannelId = TARGET_CHANNEL_ID;
        $this->postingInterval = 60; // 60 seconds between posts
        $this->webhookUrl = defined('WEBHOOK_URL') ? WEBHOOK_URL : '';

        // Initialize Telegram Bot API
        $this->botApi = new BotApi($this->botToken);

        // Initialize database connection using PDO (standardized)
        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Initialize logger
        if (!is_dir(dirname(LOG_FILE))) {
            mkdir(dirname(LOG_FILE), 0755, true);
        }
        $this->logger = new Logger('menfess_bot');
        $this->logger->pushHandler(new StreamHandler(LOG_FILE, Logger::INFO));
        
        // Initialize last processed time
        $this->lastProcessedTime = time();
    }
    
    /**
     * Set up the webhook for the bot
     * @return bool True if webhook was set successfully
     */
    public function setWebhook()
    {
        if (empty($this->webhookUrl)) {
            $this->logger->error('WEBHOOK_URL not set in environment variables');
            return false;
        }
        
        $webhookUrl = rtrim($this->webhookUrl, '/') . '/webhook.php';
        try {
            $result = $this->botApi->setWebhook(['url' => $webhookUrl]);
            
            if ($result) {
                $this->logger->info("Webhook set successfully to {$webhookUrl}");
                return true;
            } else {
                $this->logger->error('Failed to set webhook');
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception setting webhook: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove the webhook (use getUpdates instead)
     * @return bool True if webhook was removed successfully
     */
    public function removeWebhook()
    {
        try {
            $result = $this->botApi->setWebhook(['url' => '']);
            
            if ($result) {
                $this->logger->info("Webhook removed successfully");
                return true;
            } else {
                $this->logger->error('Failed to remove webhook');
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception removing webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Start the bot loop
     */
    public function start()
    {
        $this->logger->info('Menfess Bot starting...');
        
        $offset = 0;
        $running = true;
        
        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use (&$running) { $running = false; });
            pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
            pcntl_async_signals(true);
        }
        
        while ($running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            try {
                $updates = $this->botApi->getUpdates(['offset' => $offset, 'timeout' => 30]);
                
                foreach ($updates as $update) {
                    $this->processUpdate($update);
                    $offset = $update->getUpdateId() + 1;
                }
                
                // Process auto-approval queue after handling updates
                $this->processAutoApprovalQueue();
                
                // Small delay to prevent excessive CPU usage
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $this->logger->error('Error in bot loop: ' . $e->getMessage());
                sleep(5); // Wait before retrying
            }
        }
        
        $this->logger->info('Bot stopped');
    }

    /**
     * Process an incoming update
     */
    private function processUpdate(Update $update)
    {
        // Handle incoming messages
        if ($update->getMessage() && $update->getMessage()->getChat()->getType() === 'private') {
            $this->handlePrivateMessage($update->getMessage());
        }
        
        // Handle callback queries (for inline buttons)
        if ($update->getCallbackQuery()) {
            $this->handleCallbackQuery($update->getCallbackQuery());
        }
    }

    /**
     * Handle a private message
     */
    private function handlePrivateMessage($message)
    {
        $user = $message->getFrom();
        $userId = $user->getId();
        $username = $user->getUsername();
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $languageCode = $user->getLanguageCode();
        $isBot = $user->getIsBot();
        $text = $message->getText();
        
        // Check for null text (media-only messages)
        if ($text === null || empty(trim($text))) {
            $this->botApi->sendMessage($message->getChat()->getId(), "Pesan tidak boleh kosong. Bot ini hanya menerima pesan teks.");
            return;
        }
        
        $this->logger->info("Received message from user {$userId}: {$text}");

        // Store or update user information (for analytics, not for breaking anonymity)
        $this->storeUserInfo($userId, $username, $firstName, $lastName, $languageCode, $isBot);

        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handleCommand($message);
            return;
        }

        // Handle regular messages as submissions
        $this->handleSubmission($message);
    }
    
    /**
     * Handle a command message
     */
    private function handleCommand($message)
    {
        $command = strtolower($message->getText());
        $chatId = $message->getChat()->getId();
        
        switch ($command) {
            case '/start':
                $this->botApi->sendMessage($chatId, "Selamat datang di Bot Menfess!\n\nKirim pesan anonymous Anda ke bot ini, dan jika disetujui, akan diposting ke channel menfess.");
                break;
                
            case '/help':
                $helpText = "Panduan Penggunaan Bot Menfess:\n\n";
                $helpText .= "/start - Memulai bot\n";
                $helpText .= "/help - Menampilkan panduan ini\n";
                $helpText .= "/status - Melihat status bot\n\n";
                $helpText .= "Kirim pesan apa saja untuk membuat menfess anonymous.";
                $this->botApi->sendMessage($chatId, $helpText);
                break;
                
            case '/status':
                $stats = $this->getSubmissionStats();
                $statusText = "Status Bot Menfess:\n\n";
                $statusText .= "Pending: {$stats['pending']}\n";
                $statusText .= "Approved: {$stats['approved']}\n";
                $statusText .= "Rejected: {$stats['rejected']}\n";
                $statusText .= "Posted: {$stats['posted']}\n";
                $this->botApi->sendMessage($chatId, $statusText);
                break;
                
            default:
                $this->botApi->sendMessage($chatId, "Perintah tidak dikenali. Ketik /help untuk melihat panduan.");
        }
    }

    /**
     * Handle a submission message
     */
    private function handleSubmission($message)
    {
        $userId = $message->getFrom()->getId();
        $text = $message->getText();
        
        // Check for empty message
        if (empty(trim($text))) {
            $this->botApi->sendMessage($message->getChat()->getId(), "Pesan tidak boleh kosong.");
            return;
        }
        
        // Store submission in database using PDO
        $stmt = $this->pdo->prepare("INSERT INTO submissions (message_text, submitted_at) VALUES (?, NOW())");
        $stmt->execute([$text]);
        
        $submissionId = (int)$this->pdo->lastInsertId();
        
        $this->logger->info("Stored submission {$submissionId} from user {$userId}");
        
        // Calculate queue position
        $queuePosition = $this->getQueuePosition($submissionId);
        
        // Calculate estimated wait time
        $estimatedWaitSeconds = $queuePosition * $this->postingInterval;
        $waitTimeString = $this->formatWaitTime($estimatedWaitSeconds);
        
        // Send acknowledgment to user with queue and time information
        $responseMessage = "Pesan diterima! ";
        if ($queuePosition > 0) {
            $responseMessage .= "Anda berada di antrian ke-{$queuePosition}. ";
            $responseMessage .= "Estimasi waktu penayangan: {$waitTimeString}. ";
        } else {
            $responseMessage .= "Menfess Anda akan diposting dalam perkiraan {$waitTimeString}. ";
        }
        $responseMessage .= "Menfess akan diposting secara berurutan dengan jeda 1 menit antar posting.";
        
        $this->botApi->sendMessage($message->getChat()->getId(), $responseMessage);
        
        // Notify admins about new submission (in a real implementation, you'd have an admin system)
        // For now, we'll just log it
        $this->logger->info("New submission {$submissionId} awaiting auto-approval. Queue position: {$queuePosition}, Estimated wait: {$waitTimeString}");
    }
    
    /**
     * Store or update user information for analytics purposes
     * Note: This data is NOT linked to submissions to maintain anonymity
     */
    private function storeUserInfo($telegramId, $username, $firstName, $lastName, $languageCode, $isBot)
    {
        try {
            // Check if user already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update existing user
                $stmt = $this->pdo->prepare("UPDATE users SET 
                                                    username = ?, 
                                                    first_name = ?, 
                                                    last_name = ?, 
                                                    language_code = ?, 
                                                    is_bot = ?, 
                                                    last_seen = NOW(),
                                                    submission_count = submission_count + 1
                                                  WHERE telegram_id = ?");
                $stmt->execute([$username, $firstName, $lastName, $languageCode, $isBot ? 1 : 0, $telegramId]);
            } else {
                // Insert new user
                $stmt = $this->pdo->prepare("INSERT INTO users 
                                                    (telegram_id, username, first_name, last_name, language_code, is_bot, submission_count)
                                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$telegramId, $username, $firstName, $lastName, $languageCode, $isBot ? 1 : 0]);
            }
            
            $this->logger->info("Stored/updated user info for telegram_id {$telegramId}");
        } catch (\Exception $e) {
            $this->logger->error("Error storing user info: " . $e->getMessage());
            // Don't throw the exception as we don't want to break the submission flow
        }
    }

    /**
     * Handle callback query (inline button clicks)
     */
    private function handleCallbackQuery($callbackQuery)
    {
        try {
            $data = $callbackQuery->getData();
            $messageId = $callbackQuery->getMessage()->getMessageId();
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            
            // Parse callback data: action_submissionId
            if (strpos($data, '_') !== false) {
                $parts = explode('_', $data);
                $action = $parts[0];
                $submissionId = (int)$parts[1];
                
                switch ($action) {
                    case 'approve':
                        $this->approveSubmission($submissionId, $callbackQuery->getFrom()->getUsername());
                        break;
                    case 'reject':
                        $this->rejectSubmission($submissionId, $callbackQuery->getFrom()->getUsername());
                        break;
                }
                
                // Update the message to show it's been processed (with error handling)
                try {
                    $this->botApi->editMessageReplyMarkup($chatId, $messageId, '');
                } catch (\Exception $e) {
                    // Log but don't break the flow if editMessageReplyMarkup fails
                    $this->logger->error("Failed to edit message reply markup: " . $e->getMessage());
                }
            }
            
            // Answer callback query to remove loading state
            $this->botApi->answerCallbackQuery($callbackQuery->getId(), 'Processing...');
        } catch (\Exception $e) {
            $this->logger->error("Error handling callback query: " . $e->getMessage());
        }
    }

    /**
     * Show pending queue
     */
    public function showQueue()
    {
        $stmt = $this->pdo->prepare("SELECT id, message_text, submitted_at FROM submissions WHERE status = 'pending' ORDER BY submitted_at ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Approve a submission and post to channel (with transaction to prevent race conditions)
     */
    private function approveSubmission($submissionId, $moderatorUsername)
    {
        try {
            $this->pdo->beginTransaction();
            
            // Lock the row to prevent concurrent modifications
            $stmt = $this->pdo->prepare("SELECT id, message_text, status FROM submissions WHERE id = ? FOR UPDATE SKIP LOCKED");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch();
            
            if (!$submission || $submission['status'] !== 'pending') {
                $this->pdo->rollBack();
                $this->logger->warning("Submission {$submissionId} not found or already processed");
                return;
            }
            
            // Update submission status
            $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->execute([$moderatorUsername, $submissionId]);
            
            $this->pdo->commit();
            
            // Post to Telegram channel (outside transaction)
            try {
                $result = $this->botApi->sendMessage($this->targetChannelId, $submission['message_text']);
                
                if ($result && isset($result['message_id'])) {
                    // Store the posted message ID
                    $stmt = $this->pdo->prepare("INSERT INTO channel_posts (submission_id, telegram_message_id) VALUES (?, ?)");
                    $stmt->execute([$submissionId, $result['message_id']]);
                    
                    $this->logger->info("Submission {$submissionId} approved and posted to channel");
                } else {
                    $this->logger->error("Failed to post submission {$submissionId} to channel");
                    // Revert approval if posting failed
                    $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                    $stmt->execute([$submissionId]);
                }
            } catch (\Exception $e) {
                $this->logger->error("Exception posting submission to channel: " . $e->getMessage());
                // Revert approval if posting failed
                $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                $stmt->execute([$submissionId]);
            }
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error("Error approving submission: " . $e->getMessage());
        }
    }

    /**
     * Reject a submission (with transaction to prevent race conditions)
     */
    private function rejectSubmission($submissionId, $moderatorUsername)
    {
        try {
            $this->pdo->beginTransaction();
            
            // Lock the row to prevent concurrent modifications
            $stmt = $this->pdo->prepare("SELECT id, status FROM submissions WHERE id = ? FOR UPDATE SKIP LOCKED");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch();
            
            if (!$submission || $submission['status'] !== 'pending') {
                $this->pdo->rollBack();
                $this->logger->warning("Submission {$submissionId} not found or already processed");
                return;
            }
            
            // Update submission status
            $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->execute([$moderatorUsername, $submissionId]);
            
            $this->pdo->commit();
            
            $this->logger->info("Submission {$submissionId} rejected by moderator {$moderatorUsername}");
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error("Error rejecting submission: " . $e->getMessage());
        }
    }

    /**
     * Get submission statistics
     */
    private function getSubmissionStats()
    {
        $stats = [];
        
        // Pending submissions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending'] = (int)$stmt->fetch()['count'];
        
        // Approved submissions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'approved'");
        $stmt->execute();
        $stats['approved'] = (int)$stmt->fetch()['count'];
        
        // Rejected submissions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'rejected'");
        $stmt->execute();
        $stats['rejected'] = (int)$stmt->fetch()['count'];
        
        // Posted submissions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM channel_posts");
        $stmt->execute();
        $stats['posted'] = (int)$stmt->fetch()['count'];
        
        return $stats;
    }
    
    /**
     * Format wait time for display
     */
    private function formatWaitTime($seconds)
    {
        if ($seconds < 60) {
            return $seconds . " detik";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . " menit";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            if ($minutes > 0) {
                return $hours . " jam " . $minutes . " menit";
            } else {
                return $hours . " jam";
            }
        }
    }
    
    /**
     * Get queue position for a submission
     */
    private function getQueuePosition($submissionId)
    {
        // Count how many pending submissions were submitted before this one
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending' AND submitted_at <= (SELECT submitted_at FROM submissions WHERE id = ?)");
        $stmt->execute([$submissionId]);
        
        return (int)$stmt->fetch()['count'];
    }
    
    /**
     * Process auto-approval queue with proper locking to prevent race conditions
     */
    private function processAutoApprovalQueue()
    {
        // Check if enough time has passed since last post
        $currentTime = time();
        if ($currentTime - $this->lastProcessedTime < $this->postingInterval) {
            return; // Not enough time has passed, wait for next cycle
        }
        
        // Use a transaction with SELECT FOR UPDATE to prevent race conditions
        try {
            $this->pdo->beginTransaction();
            
            // Find the oldest pending submission with row-level locking
            $stmt = $this->pdo->prepare("SELECT id, message_text FROM submissions WHERE status = 'pending' ORDER BY submitted_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
            $stmt->execute();
            $submission = $stmt->fetch();
            
            if (!$submission) {
                $this->pdo->rollBack();
                return; // No pending submissions
            }
            
            $submissionId = (int)$submission['id'];
            $messageText = $submission['message_text'];
            
            $this->logger->info("Auto-approving submission {$submissionId} for posting");
            
            // Update submission status to approved
            $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'approved', reviewed_at = NOW(), reviewed_by = 'auto-approval' WHERE id = ?");
            $stmt->execute([$submissionId]);
            
            $this->pdo->commit();
            
            // Post to Telegram channel (outside transaction to avoid holding lock)
            try {
                $result = $this->botApi->sendMessage($this->targetChannelId, $messageText);
                
                if ($result && isset($result['message_id'])) {
                    // Store the posted message ID
                    $stmt = $this->pdo->prepare("INSERT INTO channel_posts (submission_id, telegram_message_id) VALUES (?, ?)");
                    $stmt->execute([$submissionId, $result['message_id']]);
                    
                    // Update last processed time
                    $this->lastProcessedTime = time();
                    
                    $this->logger->info("Submission {$submissionId} auto-approved and posted to channel");
                } else {
                    $this->logger->error("Failed to post submission {$submissionId} to channel");
                    // Revert approval if posting failed
                    $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                    $stmt->execute([$submissionId]);
                }
            } catch (\Exception $e) {
                $this->logger->error("Exception while posting submission {$submissionId}: " . $e->getMessage());
                // Revert approval if posting failed
                $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                $stmt->execute([$submissionId]);
            }
        } catch (\Exception $e) {
            // Rollback transaction on any error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error("Error in auto-approval queue: " . $e->getMessage());
        }
    }
}