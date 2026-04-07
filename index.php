<?php
/**
 * Polling mode entry point for Menfess Bot
 * Run: php index.php
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
use TelegramBot\Api\BotApi;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
if (!is_dir(dirname(LOG_FILE))) mkdir(dirname(LOG_FILE), 0755, true);
$logger = new Logger('menfess_bot');
$logger->pushHandler(new StreamHandler(LOG_FILE, Logger::INFO));
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use (&$running, $logger) { $logger->info('SIGINT shutdown'); $running = false; });
    pcntl_signal(SIGTERM, function () use (&$running, $logger) { $logger->info('SIGTERM shutdown'); $running = false; });
    pcntl_async_signals(true);
}
try {
    $api = new BotApi(TELEGRAM_BOT_TOKEN);
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $logger->info('Bot starting');
    
    // Load last processed offset from file to prevent duplicate processing after restart
    $offsetFile = __DIR__ . '/.bot_offset';
    if (file_exists($offsetFile)) {
        $savedOffset = file_get_contents($offsetFile);
        if ($savedOffset !== false) {
            $offset = (int)$savedOffset;
        }
    }
    
    $logger->info('Starting with offset: ' . $offset);
    
    while ($running) {
            if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
            
            try {
                $updates = $api->getUpdates(['offset' => $offset, 'timeout' => 30]);
            } catch (Exception $e) {
                $logger->error('Error getting updates: ' . $e->getMessage());
                sleep(5);
                continue;
            }
            
            foreach ($updates as $u) {
                $offset = $u->getUpdateId() + 1;
                
                try {
                    // Handle message updates
                    if ($u->getMessage()) {
                        $msg = $u->getMessage();
                        
                        // Safely get chat and from objects
                        $chat = $msg->getChat();
                        $from = $msg->getFrom();
                        
                        if (!$chat || !$from) {
                            continue; // Skip messages without chat or from info
                        }
                        
                        $cid = $chat->getId();
                        $txt = $msg->getText() ?? '';
                        $uid = $from->getId();
                        
                        // Check if this is from discussion group (handle comments)
                        if ((string)$cid === (string)DISCUSSION_GROUP_ID) {
                            // Convert message object to array for handleComment
                            $replyToMsg = $msg->getReplyToMessage();
                            $forwardFrom = $msg->getForwardFrom();
                            
                            $msgArray = [
                                'from' => [
                                    'id' => $from->getId(),
                                    'username' => $from->getUsername(),
                                    'first_name' => $from->getFirstName(),
                                    'last_name' => $from->getLastName(),
                                ],
                                'text' => $txt,
                                'chat' => ['id' => $cid],
                                'message_id' => $msg->getMessageId(),
                                'reply_to_message' => $replyToMsg ? [
                                    'message_id' => $replyToMsg->getMessageId()
                                ] : null,
                                'is_automatic_forward' => $forwardFrom !== null,
                            ];
                            handleComment($api, $pdo, $msgArray);
                        } elseif ($msg->getChat()->getType() === 'private') {
                            // Handle private messages
                            if (strpos($txt, '/') === 0) {
                                $cmd = strtolower($txt);
                                if ($cmd === '/help' || $cmd === '/start') {
                                    $api->sendMessage($cid, "Panduan:\n\n/status - Lihat sisa limit\n/tiers - Lihat tier\n/leaderboard - Lihat leaderboard user aktif\n\nKirim pesan untuk membuat menfess.");
                                } elseif ($cmd === '/status') {
                                    menfessHandleStatus($api, $cid, $uid, $pdo);
                                } elseif ($cmd === '/tiers') {
                                    menfessHandleTiers($api, $cid, $pdo);
                                } elseif ($cmd === '/leaderboard') {
                                    $webappUrl = WEBAPP_URL . '/leaderboard.html';
                                    $api->sendMessage($cid, "🏆 Lihat leaderboard user paling aktif!\n\nKlik link di bawah untuk membuka leaderboard:\n" . $webappUrl);
                                } else {
                                    $api->sendMessage($cid, 'Perintah tidak dikenali. Ketik /help.');
                                }
                            } else {
                                menfessHandleSubmit($api, $cid, $uid, $txt, $pdo);
                            }
                        }
                    }
                    
                    // Handle callback queries (for inline buttons)
                    if ($u->getCallbackQuery()) {
                        $callback = $u->getCallbackQuery();
                        $callbackData = $callback->getData() ?? '';
                        
                        // Safely get message objects
                        $callbackMsg = $callback->getMessage();
                        $callbackFrom = $callback->getFrom();
                        
                        if (!$callbackMsg || !$callbackFrom) {
                            $api->answerCallbackQuery($callback->getId());
                            continue;
                        }
                        
                        $callbackChatId = $callbackMsg->getChat()->getId();
                        $callbackMessageId = $callbackMsg->getMessageId();
                        $callbackUserId = $callbackFrom->getId();
                        
                        // Handle admin callbacks
                        if (in_array($callbackUserId, ADMIN_TELEGRAM_IDS, true)) {
                            if (strpos($callbackData, 'admin_') === 0) {
                                $parts = explode('_', $callbackData);
                                $action = $parts[1] ?? '';
                                
                                if ($action === 'rebuild_cache') {
                                    $results = rebuildAllCaches($pdo);
                                    $api->answerCallbackQuery($callback->getId(), 'Cache rebuilt: ' . implode(', ', $results));
                                    $api->editMessageText($callbackChatId, $callbackMessageId, 'Cache rebuild complete: ' . json_encode($results));
                                }
                            }
                        }
                        
                        // Answer callback query to remove loading state
                        $api->answerCallbackQuery($callback->getId());
                    }
                } catch (Exception $e) {
                    $logger->error('Error processing update ' . $u->getUpdateId() . ': ' . $e->getMessage());
                    // Continue processing other updates
                }
            }
            usleep(200000);
        }
        
        // Save offset to file before stopping
        if ($offset > 0) {
            file_put_contents($offsetFile, $offset);
            $logger->info('Saved offset: ' . $offset);
        }
        
    $logger->info('Bot stopped');
} catch (Exception $e) {
    $logger->error('Fatal: ' . $e->getMessage());
    echo 'Fatal: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}