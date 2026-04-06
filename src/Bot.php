<?php
require __DIR__ . '/../vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use Doctrine\DBAL\DriverManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Dotenv\Dotenv;

class MenfessBot
{
    private $botApi;
    private $connection;
    private $logger;
    private $targetChannelId;
    private $botToken;
    private $lastProcessedTime;
    private $postingInterval;

    public function __construct()
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'];
        $this->targetChannelId = $_ENV['TARGET_CHANNEL_ID'];
        $this->postingInterval = 60; // 60 seconds between posts
        $this->webhookUrl = $_ENV['WEBHOOK_URL'] ?? '';

        // Initialize Telegram Bot API
        $this->botApi = new BotApi($this->botToken);

        // Initialize database connection
        $this->connection = DriverManager::getConnection([
            'url' => sprintf(
                'mysql://%s:%s@%s/%s',
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            )
        ]);

        // Initialize logger
        $this->logger = new Logger('menfess_bot');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../bot.log', Logger::INFO));
        
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
        
        $webhookUrl = $this->webhookUrl . '/webhook.php';
        $result = $this->botApi->setWebhook(['url' => $webhookUrl]);
        
        if ($result) {
            $this->logger->info("Webhook set successfully to {$webhookUrl}");
            return true;
        } else {
            $this->logger->error('Failed to set webhook');
            return false;
        }
    }
    
    /**
     * Remove the webhook (use getUpdates instead)
     * @return bool True if webhook was removed successfully
     */
    public function removeWebhook()
    {
        $result = $this->botApi->setWebhook(['url' => '']);
        
        if ($result) {
            $this->logger->info("Webhook removed successfully");
            return true;
        } else {
            $this->logger->error('Failed to remove webhook');
            return false;
        }
    }

    public function start()
    {
        $this->logger->info('Menfess Bot starting...');
        
        // Set webhook or use getUpdates - for simplicity we'll use getUpdates
        // In production, you might want to use webhooks
        $offset = 0;
        
        while (true) {
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
    }

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

    private function handlePrivateMessage($message)
    {
        $user = $message->getFrom();
        $userId = $user->getId();
        $username = $user->getUsername();
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $languageCode = $user->getLanguageCode();
        $isBot = $user->getIsBot();
        
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
    
    // Method to handle private message from webhook array data
    private function handlePrivateMessageFromArray($message)
    {
        $user = $message['from'];
        $userId = $user['id'];
        $username = $user['username'] ?? null;
        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
        $languageCode = $user['language_code'] ?? null;
        $isBot = $user['is_bot'] ?? false;
        
        $text = $message['text'];
        
        $this->logger->info("Received message from user {$userId}: {$text}");

        // Store or update user information (for analytics, not for breaking anonymity)
        $this->storeUserInfo($userId, $username, $firstName, $lastName, $languageCode, $isBot);

        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handleCommandFromArray($message);
            return;
        }

        // Handle regular messages as submissions
        $this->handleSubmissionFromArray($message);
    }
    
    // Method to handle command from webhook array data
    private function handleCommandFromArray($message)
    {
        $command = strtolower($message['text']);
        $chatId = $message['chat']['id'];
        
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
    
    // Method to handle submission from webhook array data
    private function handleSubmissionFromArray($message)
    {
        $user = $message['from'];
        $userId = $user['id'];
        $username = $user['username'] ?? null;
        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
        $languageCode = $user['language_code'] ?? null;
        $isBot = $user['is_bot'] ?? false;
        $text = $message['text'];
        
        // Store submission in database
        $stmt = $this->connection->prepare("INSERT INTO submissions (message_text, submitted_at) VALUES (?, NOW())");
        $stmt->bindValue(1, $text);
        $stmt->execute();
        
        $submissionId = (int)$this->connection->lastInsertId();
        
        $this->logger->info("Stored submission {$submissionId} from user {$userId}");
        
        // Store or update user information (for analytics, not for breaking anonymity)
        $this->storeUserInfo($userId, $username, $firstName, $lastName, $languageCode, $isBot);
        
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
            $responseMessage .= "Menfess Anda akan diposting dalam approximasi {$waitTimeString}. ";
        }
        $responseMessage .= "Menfess akan diposting secara berurutan dengan jeda 1 menit antar posting.";
        
        $this->botApi->sendMessage($message['chat']['id'], $responseMessage);
        
        // Notify admins about new submission (in a real implementation, you'd have an admin system)
        // For now, we'll just log it
        $this->logger->info("New submission {$submissionId} awaiting auto-approval. Queue position: {$queuePosition}, Estimated wait: {$waitTimeString}");
    }
    
    // Method to handle callback query from webhook array data
    private function handleCallbackQueryFromArray($callbackQuery)
    {
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        
        // Parse callback data: action_submissionId
        if (strpos($data, '_') !== false) {
            $parts = explode('_', $data);
            $action = $parts[0];
            $submissionId = (int)$parts[1];
            
            switch ($action) {
                case 'approve':
                    $this->approveSubmission($submissionId, $callbackQuery['from']['username']);
                    break;
                case 'reject':
                    $this->rejectSubmission($submissionId, $callbackQuery['from']['username']);
                    break;
            }
            
            // Update the message to show it's been processed
            // Note: For simplicity, we're not implementing editMessageReplyMarkup for array-based updates
            // In a full implementation, you would properly handle this
        }
    }

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

    private function handleSubmission($message)
    {
        $userId = $message->getFrom()->getId();
        $text = $message->getText();
        
        // Store submission in database
        $stmt = $this->connection->prepare("INSERT INTO submissions (message_text, submitted_at) VALUES (?, NOW())");
        $stmt->bindValue(1, $text);
        $stmt->execute();
        
        $submissionId = (int)$this->connection->lastInsertId();
        
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
            $responseMessage .= "Menfess Anda akan diposting dalam approximasi {$waitTimeString}. ";
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
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->bindValue(1, $telegramId);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user) {
                // Update existing user
                $stmt = $this->connection->prepare("UPDATE users SET 
                                                    username = ?, 
                                                    first_name = ?, 
                                                    last_name = ?, 
                                                    language_code = ?, 
                                                    is_bot = ?, 
                                                    last_seen = NOW(),
                                                    submission_count = submission_count + 1
                                                  WHERE telegram_id = ?");
                $stmt->bindValue(1, $username);
                $stmt->bindValue(2, $firstName);
                $stmt->bindValue(3, $lastName);
                $stmt->bindValue(4, $languageCode);
                $stmt->bindValue(5, $isBot ? 1 : 0);
                $stmt->bindValue(6, $telegramId);
                $stmt->execute();
            } else {
                // Insert new user
                $stmt = $this->connection->prepare("INSERT INTO users 
                                                    (telegram_id, username, first_name, last_name, language_code, is_bot, submission_count)
                                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->bindValue(1, $telegramId);
                $stmt->bindValue(2, $username);
                $stmt->bindValue(3, $firstName);
                $stmt->bindValue(4, $lastName);
                $stmt->bindValue(5, $languageCode);
                $stmt->bindValue(6, $isBot ? 1 : 0);
                $stmt->execute();
            }
            
            $this->logger->info("Stored/updated user info for telegram_id {$telegramId}");
        } catch (\Exception $e) {
            $this->logger->error("Error storing user info: " . $e->getMessage());
            // Don't throw the exception as we don't want to break the submission flow
        }
    }

    private function handleCallbackQuery($callbackQuery)
    {
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
            
            // Update the message to show it's been processed
            $this->botApi->editMessageReplyMarkup($chatId, $messageId, '');
        }
    }

    public function showQueue()
    {
        // This would typically be called via a command or admin interface
        $stmt = $this->connection->prepare("SELECT id, message_text, submitted_at FROM submissions WHERE status = 'pending' ORDER BY submitted_at ASC");
        $stmt->execute();
        $submissions = $stmt->fetchAll();
        
        return $submissions;
    }

    private function approveSubmission($submissionId, $moderatorUsername)
    {
        // Update submission status
        $stmt = $this->connection->prepare("UPDATE submissions SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->bindValue(1, $moderatorUsername);
        $stmt->bindValue(2, $submissionId);
        $stmt->execute();
        
        // Get the submission text
        $stmt = $this->connection->prepare("SELECT message_text FROM submissions WHERE id = ?");
        $stmt->bindValue(1, $submissionId);
        $stmt->execute();
        $submission = $stmt->fetch();
        
        if ($submission) {
            // Post to Telegram channel
            $result = $this->botApi->sendMessage($this->targetChannelId, $submission['message_text']);
            
            if ($result && isset($result['message_id'])) {
                // Store the posted message ID
                $stmt = $this->connection->prepare("INSERT INTO channel_posts (submission_id, telegram_message_id) VALUES (?, ?)");
                $stmt->bindValue(1, $submissionId);
                $stmt->bindValue(2, $result['message_id']);
                $stmt->execute();
                
                $this->logger->info("Submission {$submissionId} approved and posted to channel");
            } else {
                $this->logger->error("Failed to post submission {$submissionId} to channel");
                // Revert approval if posting failed
                $stmt = $this->connection->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                $stmt->bindValue(1, $submissionId);
                $stmt->execute();
            }
        }
    }

    private function rejectSubmission($submissionId, $moderatorUsername)
    {
        // Update submission status
        $stmt = $this->connection->prepare("UPDATE submissions SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->bindValue(1, $moderatorUsername);
        $stmt->bindValue(2, $submissionId);
        $stmt->execute();
        
        $this->logger->info("Submission {$submissionId} rejected by moderator {$moderatorUsername}");
    }

    private function getSubmissionStats()
    {
        $stats = [];
        
        // Pending submissions
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['pending'] = (int)$result['count'];
        
        // Approved submissions
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'approved'");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['approved'] = (int)$result['count'];
        
        // Rejected submissions
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'rejected'");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['rejected'] = (int)$result['count'];
        
        // Posted submissions
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM channel_posts");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['posted'] = (int)$result['count'];
        
        return $stats;
    }
    
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
    
    private function getQueuePosition($submissionId)
    {
        // Count how many pending submissions were submitted before this one
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending' AND submitted_at <= (SELECT submitted_at FROM submissions WHERE id = ?)");
        $stmt->bindValue(1, $submissionId);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    private function processAutoApprovalQueue()
    {
        // Check if enough time has passed since last post
        $currentTime = time();
        if ($currentTime - $this->lastProcessedTime < $this->postingInterval) {
            return; // Not enough time has passed, wait for next cycle
        }
        
        // Find the oldest pending submission
        $stmt = $this->connection->prepare("SELECT id, message_text FROM submissions WHERE status = 'pending' ORDER BY submitted_at ASC LIMIT 1");
        $stmt->execute();
        $submission = $stmt->fetch();
        
        if ($submission) {
            $submissionId = (int)$submission['id'];
            $messageText = $submission['message_text'];
            
            $this->logger->info("Auto-approving submission {$submissionId} for posting");
            
            // Update submission status to approved
            $stmt = $this->connection->prepare("UPDATE submissions SET status = 'approved', reviewed_at = NOW(), reviewed_by = 'auto-approval' WHERE id = ?");
            $stmt->bindValue(1, $submissionId);
            $stmt->execute();
            
            // Post to Telegram channel
            try {
                $result = $this->botApi->sendMessage($this->targetChannelId, $messageText);
                
                if ($result && isset($result['message_id'])) {
                    // Store the posted message ID
                    $stmt = $this->connection->prepare("INSERT INTO channel_posts (submission_id, telegram_message_id) VALUES (?, ?)");
                    $stmt->bindValue(1, $submissionId);
                    $stmt->bindValue(2, $result['message_id']);
                    $stmt->execute();
                    
                    // Update last processed time
                    $this->lastProcessedTime = time();
                    
                    $this->logger->info("Submission {$submissionId} auto-approved and posted to channel");
                } else {
                    $this->logger->error("Failed to post submission {$submissionId} to channel");
                    // Revert approval if posting failed
                    $stmt = $this->connection->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                    $stmt->bindValue(1, $submissionId);
                    $stmt->execute();
                }
            } catch (\Exception $e) {
                $this->logger->error("Exception while posting submission {$submissionId}: " . $e->getMessage());
                // Revert approval if posting failed
                $stmt = $this->connection->prepare("UPDATE submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
                $stmt->bindValue(1, $submissionId);
                $stmt->execute();
            }
        }
    }
}

// Run the bot
$bot = new MenfessBot();
$bot->start();