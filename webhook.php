<?php
/**
 * Webhook mode entry point for Menfess Bot
 * Handles both private messages and discussion group comments
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

use TelegramBot\Api\BotApi;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if (!is_dir(dirname(LOG_FILE))) mkdir(dirname(LOG_FILE), 0755, true);
$logger = new Logger('menfess_webhook');
$logger->pushHandler(new StreamHandler(LOG_FILE, Logger::INFO));

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(200);
    exit;
}

try {
    $api = new BotApi(TELEGRAM_BOT_TOKEN);
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Handle message
    $msg = $data['message'] ?? $data['edited_message'] ?? null;
    
    if ($msg) {
        $cid = $msg['chat']['id'] ?? null;
        $txt = $msg['text'] ?? '';
        $uid = $msg['from']['id'] ?? $cid;
        
        // Check if this is from discussion group (strict string comparison for Telegram IDs)
        if ((string)$cid === (string)DISCUSSION_GROUP_ID) {
            // Handle comment from discussion group
            handleComment($api, $pdo, $msg);
        } elseif ($msg['chat']['type'] === 'private') {
            // Handle private message (submission or command)
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
                    // sendMessage parameters: chat_id, text, parse_mode, disable_web_page_preview, disable_notification, reply_to_message_id, reply_markup
                    $api->sendMessage($cid, "🏆 Lihat leaderboard user paling aktif!\n\nKlik tombol di bawah untuk membuka leaderboard:", null, null, null, null, [
                        'reply_markup' => [
                            'inline_keyboard' => [[
                                ['text' => '🏆 Buka Leaderboard', 'url' => $webappUrl]
                            ]]
                        ]
                    ]);
                } else {
                    $api->sendMessage($cid, 'Perintah tidak dikenali. Ketik /help.');
                }
            } else {
                menfessHandleSubmit($api, $cid, $uid, $txt, $pdo);
            }
        }
    }
    
    // Handle callback query (for inline buttons)
    if (isset($data['callback_query'])) {
        $callbackData = $data['callback_query']['data'] ?? '';
        $callbackChatId = $data['callback_query']['message']['chat']['id'] ?? null;
        $callbackMessageId = $data['callback_query']['message']['message_id'] ?? null;
        $callbackUserId = $data['callback_query']['from']['id'] ?? null;
        
        // Handle admin callbacks (strict type comparison for Telegram IDs)
        if (in_array($callbackUserId, ADMIN_TELEGRAM_IDS, true)) {
            if (strpos($callbackData, 'admin_') === 0) {
                // Handle admin actions
                $parts = explode('_', $callbackData);
                $action = $parts[1] ?? '';
                
                if ($action === 'rebuild_cache') {
                    $results = rebuildAllCaches($pdo);
                    $api->answerCallbackQuery($data['callback_query']['id'], 'Cache rebuilt: ' . implode(', ', $results));
                    $api->editMessageText($callbackChatId, $callbackMessageId, 'Cache rebuild complete: ' . json_encode($results));
                }
            }
        }
    }
    
    http_response_code(200);
} catch (Exception $e) {
    $logger->error($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}