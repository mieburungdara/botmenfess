<?php
/**
 * Shared helper functions for tier-based menfess bot
 * Included by both index.php and webhook.php
 */

// Get user tier info
function menfessGetUserTier($pdo, $uid) {
    $stmt = $pdo->prepare('SELECT t.name, t.daily_limit FROM user_tiers ut INNER JOIN tiers t ON ut.tier_id = t.id WHERE ut.user_id = ? AND ut.is_active = 1 AND (ut.expires_at IS NULL OR ut.expires_at > NOW()) ORDER BY ut.started_at DESC LIMIT 1');
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: ['name' => 'free', 'daily_limit' => 3];
}

// Get today usage count (BUG 1 FIX: safe fetch)
function menfessGetTodayUsage($pdo, $uid) {
    $stmt = $pdo->prepare('SELECT count FROM daily_usage WHERE user_id = ? AND usage_date = CURDATE()');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    return $row ? (int)$row['count'] : 0;
}

// Check if user is within limit - returns [bool, message]
function menfessCheckLimit($pdo, $api, $cid, $uid) {
    $tier = menfessGetUserTier($pdo, $uid);
    $dl = (int)$tier['daily_limit'];
    if ($dl == -1) {
        return true;
    }
    $uc = menfessGetTodayUsage($pdo, $uid);
    if ($uc >= $dl) {
        $api->sendMessage($cid, 'Limit harian habis (' . $dl . ' menfess/hari). Upgrade tier!');
        return false;
    }
    return true;
}

// Record submission usage
function menfessRecordUsage($pdo, $uid) {
    $stmt = $pdo->prepare('INSERT INTO daily_usage (user_id, usage_date, count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE count = count + 1');
    $stmt->execute([$uid, date('Y-m-d')]);
}

function menfessHandleStatus($api, $cid, $uid, $pdo) {
    $tier = menfessGetUserTier($pdo, $uid);
    $lm = (int)$tier['daily_limit'];
    $us = menfessGetTodayUsage($pdo, $uid);
    $r  = "Status:\n";
    $r .= "- Tier: " . $tier['name'] . "\n";
    $r .= "- Limit: " . ($lm==-1?"Unlimited":$lm." menfess/hari") . "\n";
    $r .= "- Terpakai: " . $us . "\n";
    $r .= "- Sisa: " . ($lm==-1?"Unlimited":max(0,$lm-$us));
    $api->sendMessage($cid, $r);
}

function menfessHandleTiers($api, $cid, $pdo) {
    $tiers = $pdo->query('SELECT * FROM tiers ORDER BY daily_limit ASC')->fetchAll();
    $r = "Tier Tersedia:\n\n";
    foreach ($tiers as $t) {
        $lx = (int)$t['daily_limit'];
        $ls = ($lx==-1)?"Unlimited":$lx." menfess/hari";
        $pr = number_format($t['price'],0,',','.');
        $r .= "- " . $t['name'] . ": " . $ls . " - Rp " . $pr . "\n";
    }
    $r .= "\nHubungi admin untuk upgrade tier.";
    $api->sendMessage($cid, $r);
}

// BUG 2 FIX: now saves/extraxts user info
function menfessHandleSubmit($api, $cid, $uid, $txt, $pdo) {
    if (empty(trim($txt))) return;
    // Save/update user
    $stmt = $pdo->prepare('INSERT INTO users (telegram_id, first_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()');
    $stmt->execute([$uid]);
    if (!menfessCheckLimit($pdo, $api, $cid, $uid)) return;
    menfessRecordUsage($pdo, $uid);
    $pdo->prepare('INSERT INTO submissions (message_text) VALUES (?)')->execute([$txt]);
    $api->sendMessage($cid, 'Pesan menfess berhasil dikirim!');
}