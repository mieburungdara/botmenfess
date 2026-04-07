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
    $offset = 0;
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
                        $cid = $msg->getChat()->getId();
                        $txt = $msg->getText() ?? '';
                        $uid = $msg->getFrom()->getId();
                        
                        // Check if this is from discussion group (handle comments)
                        if ((string)$cid === (string)DISCUSSION_GROUP_ID) {
                            // Convert message object to array for handleComment
                            $msgArray = [
                                'from' => [
                                    'id' => $msg->getFrom()->getId(),
                                    'username' => $msg->getFrom()->getUsername(),
                                    'first_name' => $msg->getFrom()->getFirstName(),
                                    'last_name' => $msg->getFrom()->getLastName(),
                                ],
                                'text' => $txt,
                                'chat' => ['id' => $cid],
                                'message_id' => $msg->getMessageId(),
                                'reply_to_message' => $msg->getReplyToMessage() ? [
                                    'message_id' => $msg->getReplyToMessage()->getMessageId()
                                ] : null,
                                'is_automatic_forward' => $msg->getForwardFrom() !== null,
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
                        $callbackChatId = $callback->getMessage()->getChat()->getId();
                        $callbackMessageId = $callback->getMessage()->getMessageId();
                        $callbackUserId = $callback->getFrom()->getId();
                        
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
    $logger->info('Bot stopped');
} catch (Exception $e) {
    $logger->error('Fatal: ' . $e->getMessage());
    echo 'Fatal: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}