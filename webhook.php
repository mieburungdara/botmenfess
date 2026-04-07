<?php
/**
 * Webhook mode entry point for Menfess Bot
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

if (!$data || !isset($data['message'])) {
    http_response_code(200);
    exit;
}

try {
    $api = new BotApi(TELEGRAM_BOT_TOKEN);
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $msg = $data['message'];
    $cid = $msg['chat']['id'];
    $txt = $msg['text'] ?? '';
    $uid = $msg['from']['id'] ?? $cid;
    if (strpos($txt, '/') === 0) {
        $cmd = strtolower($txt);
        if ($cmd === '/help' || $cmd === '/start') {
            $api->sendMessage($cid, "Panduan:\n\n/status - Lihat sisa limit\n/tiers - Lihat tier\n\nKirim pesan untuk membuat menfess.");
        } elseif ($cmd === '/status') menfessHandleStatus($api, $cid, $uid, $pdo);
        elseif ($cmd === '/tiers') menfessHandleTiers($api, $cid, $pdo);
        else $api->sendMessage($cid, 'Perintah tidak dikenali. Ketik /help.');
    } else {
        menfessHandleSubmit($api, $cid, $uid, $txt, $pdo);
    }
    http_response_code(200);
} catch (Exception $e) {
    $logger->error($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}