<?php
/**
 * Shared helper functions for tier-based menfess bot
 * Included by both index.php and webhook.php
 */

// ============================================
// TIER & SUBMISSION FUNCTIONS
// ============================================

// Get user tier info
function menfessGetUserTier($pdo, $uid) {
    $stmt = $pdo->prepare('SELECT t.name, t.daily_limit FROM user_tiers ut INNER JOIN tiers t ON ut.tier_id = t.id INNER JOIN users u ON ut.user_id = u.id WHERE u.telegram_id = ? AND ut.is_active = 1 AND (ut.expires_at IS NULL OR ut.expires_at > NOW()) ORDER BY ut.started_at DESC LIMIT 1');
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: ['name' => 'free', 'daily_limit' => 3];
}

// Get today usage count
function menfessGetTodayUsage($pdo, $uid) {
    $stmt = $pdo->prepare('SELECT count FROM daily_usage du INNER JOIN users u ON du.user_id = u.id WHERE u.telegram_id = ? AND du.usage_date = CURDATE()');
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
    $stmt = $pdo->prepare('INSERT INTO daily_usage (user_id, usage_date, count) SELECT id, CURDATE(), 1 FROM users WHERE telegram_id = ? ON DUPLICATE KEY UPDATE count = count + 1');
    $stmt->execute([$uid]);
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

// Handle submission from private message
function menfessHandleSubmit($api, $cid, $uid, $txt, $pdo) {
    if (empty(trim($txt))) return;
    if (!menfessCheckLimit($pdo, $api, $cid, $uid)) return;
    // Save/update user (only if submission is allowed)
    // Use getOrCreateUser to properly handle user creation/updates
    getOrCreateUser($pdo, $uid);
    menfessRecordUsage($pdo, $uid);
    $pdo->prepare('INSERT INTO submissions (message_text) VALUES (?)')->execute([$txt]);
    $pdo->prepare('UPDATE users SET last_seen = NOW(), submission_count = submission_count + 1 WHERE telegram_id = ?')->execute([$uid]);
    $api->sendMessage($cid, 'Pesan menfess berhasil dikirim!');
}

// ============================================
// USER MANAGEMENT FUNCTIONS
// ============================================

// Get or create user by telegram_id, returns user_id
function getOrCreateUser($pdo, $telegramId, $username = null, $firstName = null, $lastName = null) {
    // Check if user exists
    $stmt = $pdo->prepare('SELECT id, username, first_name FROM users WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update user info
        $stmt = $pdo->prepare('UPDATE users SET username = ?, first_name = ?, last_name = ?, last_seen = NOW() WHERE telegram_id = ?');
        $stmt->execute([$username, $firstName, $lastName, $telegramId]);
        return $user['id'];
    }
    
    // Create new user
    $stmt = $pdo->prepare('INSERT INTO users (telegram_id, username, first_name, last_name, first_seen, last_seen) VALUES (?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([$telegramId, $username, $firstName, $lastName]);
    return (int)$pdo->lastInsertId();
}

// Check if user is shadow banned
function isUserShadowBanned($pdo, $telegramId) {
    $stmt = $pdo->prepare('SELECT is_shadow_banned FROM users WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();
    return $row && (bool)$row['is_shadow_banned'];
}

// Shadow ban a user
function shadowBanUser($pdo, $telegramId, $adminUsername = 'system') {
    $stmt = $pdo->prepare('UPDATE users SET is_shadow_banned = 1, shadow_banned_at = NOW(), shadow_banned_by = ? WHERE telegram_id = ?');
    $stmt->execute([$adminUsername, $telegramId]);
}

// Unshadow ban a user
function unshadowBanUser($pdo, $telegramId) {
    $stmt = $pdo->prepare('UPDATE users SET is_shadow_banned = 0, shadow_banned_at = NULL, shadow_banned_by = NULL WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
}

// ============================================
// COMMENT HANDLING FUNCTIONS
// ============================================

// Validate comment before saving
function validateComment($pdo, $telegramId, $text) {
    $errors = [];
    
    // Check minimum length
    if (strlen(trim($text)) < MIN_COMMENT_LENGTH) {
        $errors[] = "Komentar terlalu pendek. Minimal " . MIN_COMMENT_LENGTH . " karakter.";
    }
    
    // Check rate limit - only block if user has exceeded the rate
    // Rate limit: max 1 comment per COMMENT_RATE_LIMIT_SECONDS window
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE u.telegram_id = ? AND c.created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)');
    $stmt->execute([$telegramId, COMMENT_RATE_LIMIT_SECONDS]);
    $row = $stmt->fetch();
    if ($row && (int)$row['count'] >= 1) {
        $waitSeconds = COMMENT_RATE_LIMIT_SECONDS;
        $errors[] = "Tunggu {$waitSeconds} detik sebelum mengirim komentar lagi.";
    }
    
    // Check duplicate
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE u.telegram_id = ? AND c.comment_text = ? AND c.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)');
    $stmt->execute([$telegramId, trim($text), COMMENT_DUPLICATE_CHECK_HOURS]);
    $row = $stmt->fetch();
    if ($row && (int)$row['count'] > 0) {
        $errors[] = "Komentar serupa baru saja dikirim.";
    }
    
    return $errors;
}

// Save comment to database
function saveComment($pdo, $telegramId, $submissionId, $text, $telegramMessageId = null, $replyToMessageId = null) {
    // Get or create user
    $userId = getOrCreateUser($pdo, $telegramId);
    
    // Check shadow ban - if shadow banned, don't save comment or award badges
    $isShadowBanned = isUserShadowBanned($pdo, $telegramId);
    if ($isShadowBanned) {
        // Shadow banned users' comments are silently ignored
        return null;
    }
    
    // Save comment
    $stmt = $pdo->prepare('INSERT INTO comments (submission_id, user_id, comment_text, telegram_message_id, reply_to_message_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$submissionId, $userId, trim($text), $telegramMessageId, $replyToMessageId]);
    $commentId = (int)$pdo->lastInsertId();
    
    // Log to history
    $stmt = $pdo->prepare('INSERT INTO comment_history (user_id, comment_id, action) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $commentId, 'created']);
    
    // Check and award badges (only for non-shadow-banned users)
    checkAndAwardBadges($pdo, $userId, $telegramId);
    
    // Check and update auto titles
    checkAndUpdateTitles($pdo, $userId, $telegramId);
    
    return $commentId;
}

// Find submission_id from reply_to_message_id
function findSubmissionFromReply($pdo, $replyToMessageId) {
    // First check if it's a reply to a channel post
    $stmt = $pdo->prepare('SELECT submission_id FROM channel_posts WHERE telegram_message_id = ?');
    $stmt->execute([$replyToMessageId]);
    $row = $stmt->fetch();
    
    if ($row) {
        return (int)$row['submission_id'];
    }
    
    // Check if it's a reply to another comment's telegram_message_id
    $stmt = $pdo->prepare('SELECT c.submission_id FROM comments c WHERE c.telegram_message_id = ?');
    $stmt->execute([$replyToMessageId]);
    $row = $stmt->fetch();
    
    if ($row) {
        return (int)$row['submission_id'];
    }
    
    return null;
}

// Handle comment from discussion group
function handleComment($api, $pdo, $message) {
    $userId = $message['from']['id'] ?? null;
    $username = $message['from']['username'] ?? null;
    $firstName = $message['from']['first_name'] ?? null;
    $lastName = $message['from']['last_name'] ?? null;
    $text = $message['text'] ?? '';
    $chatId = $message['chat']['id'] ?? null;
    $messageId = $message['message_id'] ?? null;
    $replyToMessageId = $message['reply_to_message']['message_id'] ?? null;
    
    if (!$userId || empty(trim($text))) {
        return;
    }
    
    // Verify this is from discussion group (compare as strings to handle int/string mismatch)
    if ((string)$chatId !== (string)DISCUSSION_GROUP_ID) {
        return;
    }
    
    // Get or create user
    getOrCreateUser($pdo, $userId, $username, $firstName, $lastName);
    
    // Check shadow ban - silently ignore (shadow banned users' comments are not counted)
    if (isUserShadowBanned($pdo, $userId)) {
        return;
    }
    
    // Find submission from reply
    $submissionId = null;
    if ($replyToMessageId) {
        $submissionId = findSubmissionFromReply($pdo, $replyToMessageId);
    }
    
    // If no submission found, try to find from message thread
    if (!$submissionId && isset($message['is_automatic_forward']) && $message['is_automatic_forward']) {
        // This is an auto-forwarded message from channel
        $submissionId = findSubmissionFromReply($pdo, $messageId);
    }
    
    if (!$submissionId) {
        // Can't associate comment with submission, ignore
        return;
    }
    
    // Validate comment
    $errors = validateComment($pdo, $userId, $text);
    if (!empty($errors)) {
        // sendMessage with reply_to_message_id: chat_id, text, parse_mode, disable_web_page_preview, disable_notification, reply_to_message_id
        // Note: 5th param disable_notification=false so user actually sees error messages
        $api->sendMessage($chatId, implode("\n", $errors), null, null, false, $messageId);
        return;
    }
    
    // Save comment
    $commentId = saveComment($pdo, $userId, $submissionId, $text, $messageId, $replyToMessageId);
    
    if ($commentId) {
        // Get comment count for feedback
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE u.telegram_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        $totalCount = $row ? (int)$row['count'] : 0;
        
        // Send acknowledgment (correct parameter order: chat_id, text, parse_mode, disable_web_page_preview, disable_notification, reply_to_message_id, reply_markup)
        // Note: 5th param disable_notification=false so user gets notified, 6th param reply_to_message_id
        $api->sendMessage($chatId, "✅ Komentar tersimpan! Total komentar kamu: " . $totalCount, null, null, false, $messageId);
    }
}

// ============================================
// BADGE SYSTEM FUNCTIONS
// ============================================

// Check and award badges for a user
function checkAndAwardBadges($pdo, $userId, $telegramId = null) {
    $badges = checkBadgeEligibility($pdo, $userId);
    
    foreach ($badges as $badgeKey) {
        // Send notification BEFORE awarding (so the badge check in sendBadgeNotification works)
        if ($telegramId) {
            sendBadgeNotificationBeforeAward($pdo, $telegramId, $badgeKey);
        }
        
        // Then award the badge
        awardBadge($pdo, $userId, $badgeKey);
    }
}

// Send badge notification before the badge is awarded
function sendBadgeNotificationBeforeAward($pdo, $telegramId, $badgeKey) {
    // Double-check user doesn't already have this badge (prevent duplicate notifications)
    $stmt = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = (SELECT id FROM users WHERE telegram_id = ?) AND badge_key = ?');
    $stmt->execute([$telegramId, $badgeKey]);
    if ($stmt->fetch()) {
        return; // Already has badge, skip notification
    }
    
    $badges = BADGES;
    if (!isset($badges[$badgeKey])) {
        return;
    }
    
    $badge = $badges[$badgeKey];
    $message = "🎉 Selamat! Kamu mendapatkan badge {$badge['icon']} {$badge['name']}!\n\n{$badge['description']}";
    
    try {
        $api = new TelegramBot\Api\BotApi(TELEGRAM_BOT_TOKEN);
        $api->sendMessage($telegramId, $message);
    } catch (Exception $e) {
        // Ignore notification errors (user may have blocked bot)
    }
}

// Check which badges a user is eligible for
function checkBadgeEligibility($pdo, $userId) {
    $eligibleBadges = [];
    
    // Get user's current badges
    $stmt = $pdo->prepare('SELECT badge_key FROM user_badges WHERE user_id = ?');
    $stmt->execute([$userId]);
    $existingBadges = array_column($stmt->fetchAll(), 'badge_key');
    
    // First check if user is shadow banned - if so, don't award any badges
    // Note: We need to get telegram_id from user_id first
    $stmt = $pdo->prepare('SELECT telegram_id, is_shadow_banned FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || (bool)$user['is_shadow_banned']) {
        return $eligibleBadges; // Shadow banned users get no badges
    }
    
    // Newcomer: First comment
    if (!in_array('newcomer', $existingBadges)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM comments WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row && (int)$row['count'] >= 1) {
            $eligibleBadges[] = 'newcomer';
        }
    }
    
    // Active Commenter: 100+ comments
    if (!in_array('active_commenter', $existingBadges)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM comments WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row && (int)$row['count'] >= 100) {
            $eligibleBadges[] = 'active_commenter';
        }
    }
    
    // Early Bird: 50%+ comments between 06-09
    if (!in_array('early_bird', $existingBadges)) {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN HOUR(created_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) as early_count
            FROM comments 
            WHERE user_id = ?
        ');
        $stmt->execute([EARLY_BIRD_START, EARLY_BIRD_END, $userId]);
        $row = $stmt->fetch();
        if ($row && (int)$row['total'] >= 10) {
            $percentage = ((int)$row['early_count'] / (int)$row['total']) * 100;
            if ($percentage >= 50) {
                $eligibleBadges[] = 'early_bird';
            }
        }
    }
    
    // Night Owl: 50%+ comments between 23-02
    if (!in_array('night_owl', $existingBadges)) {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN HOUR(created_at) >= ? OR HOUR(created_at) <= ? THEN 1 ELSE 0 END) as night_count
            FROM comments 
            WHERE user_id = ?
        ');
        $stmt->execute([NIGHT_OWL_START, NIGHT_OWL_END, $userId]);
        $row = $stmt->fetch();
        if ($row && (int)$row['total'] >= 10) {
            $percentage = ((int)$row['night_count'] / (int)$row['total']) * 100;
            if ($percentage >= 50) {
                $eligibleBadges[] = 'night_owl';
            }
        }
    }
    
    // Streak Master: 7+ consecutive days
    if (!in_array('streak_master', $existingBadges)) {
        $streak = calculateStreak($pdo, $userId);
        if ($streak >= 7) {
            $eligibleBadges[] = 'streak_master';
        }
    }
    
    // Conversation Starter: Has commented on a submission that has 20+ total comments
    if (!in_array('conversation_starter', $existingBadges)) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM comments c2 
            WHERE c2.submission_id IN (
                SELECT DISTINCT c.submission_id FROM comments WHERE user_id = ?
            )
            GROUP BY c2.submission_id
            HAVING COUNT(*) >= 20
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            $eligibleBadges[] = 'conversation_starter';
        }
    }
    
    return $eligibleBadges;
}

// Award a badge to a user
function awardBadge($pdo, $userId, $badgeKey) {
    // Check if already has badge
    $stmt = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = ? AND badge_key = ?');
    $stmt->execute([$userId, $badgeKey]);
    if ($stmt->fetch()) {
        return false;
    }
    
    $stmt = $pdo->prepare('INSERT INTO user_badges (user_id, badge_key, metadata) VALUES (?, ?, ?)');
    $metadata = json_encode(['awarded_at' => date('Y-m-d H:i:s')]);
    $stmt->execute([$userId, $badgeKey, $metadata]);
    return true;
}

// Send badge notification via bot (used by cron job - checks if badge exists first)
function sendBadgeNotification($pdo, $telegramId, $badgeKey) {
    // Check if user has this badge already (badge table is source of truth)
    $stmt = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = (SELECT id FROM users WHERE telegram_id = ?) AND badge_key = ?');
    $stmt->execute([$telegramId, $badgeKey]);
    if ($stmt->fetch()) {
        // Badge already exists, notification already sent
        return;
    }
    
    $badges = BADGES;
    if (!isset($badges[$badgeKey])) {
        return;
    }
    
    $badge = $badges[$badgeKey];
    $message = "🎉 Selamat! Kamu mendapatkan badge {$badge['icon']} {$badge['name']}!\n\n{$badge['description']}";
    
    try {
        $api = new TelegramBot\Api\BotApi(TELEGRAM_BOT_TOKEN);
        $api->sendMessage($telegramId, $message);
    } catch (Exception $e) {
        // Ignore notification errors (user may have blocked bot)
    }
}

// Calculate consecutive day streak (requires consecutive days, no gaps)
function calculateStreak($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT DISTINCT DATE(created_at) as comment_date 
        FROM comments 
        WHERE user_id = ? 
        ORDER BY comment_date DESC
    ');
    $stmt->execute([$userId]);
    $dates = array_column($stmt->fetchAll(), 'comment_date');
    
    if (empty($dates)) {
        return 0;
    }
    
    $streak = 1;
    try {
        $currentDate = new DateTime($dates[0]);
        
        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = new DateTime($dates[$i]);
            $diff = $currentDate->diff($prevDate);
            
            // Allow 1-day gap (diff == 1 means consecutive days)
            if ($diff->days == 1) {
                $streak++;
                $currentDate = $prevDate;
            } else {
                break;
            }
        }
    } catch (Exception $e) {
        // Return 0 if date parsing fails
        return 0;
    }
    
    return $streak;
}

// ============================================
// LEADERBOARD FUNCTIONS
// ============================================

// Get leaderboard data (from cache or regenerate)
function getLeaderboard($pdo, $timeframe = 'alltime', $limit = LEADERBOARD_DEFAULT_LIMIT) {
    // Check cache
    $cache = getLeaderboardCache($pdo, $timeframe);
    
    if ($cache && !isCacheExpired($cache['generated_at'])) {
        $data = json_decode($cache['cache_data'], true);
        $data['cached'] = true;
        $data['generated_at'] = $cache['generated_at'];
        return $data;
    }
    
    // Regenerate cache
    return rebuildLeaderboardCache($pdo, $timeframe, $limit);
}

// Get cached leaderboard
function getLeaderboardCache($pdo, $timeframe) {
    $stmt = $pdo->prepare('SELECT cache_data, generated_at FROM leaderboard_cache WHERE timeframe = ?');
    $stmt->execute([$timeframe]);
    return $stmt->fetch();
}

// Check if cache is expired
function isCacheExpired($generatedAt) {
    $generated = new DateTime($generatedAt);
    $now = new DateTime();
    $diff = $now->diff($generated);
    return $diff->h >= LEADERBOARD_CACHE_HOURS;
}

// Rebuild leaderboard cache
function rebuildLeaderboardCache($pdo, $timeframe, $limit = LEADERBOARD_DEFAULT_LIMIT) {
    $data = generateLeaderboardData($pdo, $timeframe, $limit);
    
    $cacheData = json_encode($data);
    
    $stmt = $pdo->prepare('INSERT INTO leaderboard_cache (timeframe, cache_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE cache_data = ?, generated_at = NOW()');
    $stmt->execute([$timeframe, $cacheData, $cacheData]);
    
    return $data;
}

// Generate leaderboard data from database
function generateLeaderboardData($pdo, $timeframe, $limit) {
    // Validate timeframe
    if (!in_array($timeframe, ['weekly', 'monthly', 'alltime'])) {
        $timeframe = 'alltime';
    }
    
    // Validate and sanitize limit
    $limit = max(1, min(100, (int)$limit));
    
    // Build date filter
    $dateFilter = '';
    switch ($timeframe) {
        case 'weekly':
            $dateFilter = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'monthly':
            $dateFilter = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        default:
            $dateFilter = '';
    }
    
    // Get top commenters
    // Velocity multiplier based on timeframe: weekly=7, monthly=30, alltime=30
    $velocityMultiplier = ($timeframe === 'weekly') ? 7 : 30;
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            u.first_name,
            COUNT(c.id) as comment_count,
            ROUND(COUNT(c.id) / GREATEST(DATEDIFF(NOW(), MIN(c.created_at)), 1) * $velocityMultiplier, 1) as comment_velocity,
            (
                SELECT HOUR(c3.created_at) 
                FROM comments c3 
                WHERE c3.user_id = u.id 
                $dateFilter
                GROUP BY HOUR(c3.created_at) 
                ORDER BY COUNT(*) DESC 
                LIMIT 1
            ) as most_active_hour
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        WHERE u.is_shadow_banned = 0
        $dateFilter
        GROUP BY u.id, u.username, u.first_name
        ORDER BY comment_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $users = $stmt->fetchAll();
    
    // Defensive: handle empty results
    if (empty($users)) {
        return [
            'timeframe' => $timeframe,
            'leaderboard' => [],
            'stats' => getOverallStats($pdo, $timeframe),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Get previous ranks for trend calculation
    $prevRanks = getPreviousRanks($pdo, $timeframe);
    
    // Build leaderboard array
    $leaderboard = [];
    $rank = 0;
    foreach ($users as $user) {
        $rank++;
        $prevRank = $prevRanks[$user['user_id']] ?? null;
        $trend = 'same';
        if ($prevRank !== null) {
            if ($prevRank > $rank) {
                $trend = 'up';
            } elseif ($prevRank < $rank) {
                $trend = 'down';
            }
        }
        
        // Get badges
        $badges = getUserBadges($pdo, $user['user_id']);
        
        // Determine primary badge
        $primaryBadge = null;
        if ($rank == 1 && $timeframe == 'weekly') {
            $primaryBadge = 'weekly_champion';
        } elseif ($rank <= 3 && $timeframe == 'monthly') {
            $primaryBadge = 'monthly_legend';
        } elseif ($rank == 1 && $timeframe == 'alltime') {
            $primaryBadge = 'alltime_king';
        }
        
        $leaderboard[] = [
            'rank' => $rank,
            'user_id' => $user['user_id'],
            'username' => $user['username'] ? '@' . $user['username'] : ($user['first_name'] ?? 'Anonymous'),
            'comment_count' => (int)$user['comment_count'],
            'comment_velocity' => (float)$user['comment_velocity'],
            'most_active_hour' => (int)($user['most_active_hour'] ?? 0),
            'badges' => $badges,
            'primary_badge' => $primaryBadge,
            'trend' => $trend
        ];
    }
    
    // Get overall stats (with timeframe)
    $stats = getOverallStats($pdo, $timeframe);
    
    return [
        'timeframe' => $timeframe,
        'leaderboard' => $leaderboard,
        'stats' => $stats,
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

// Get previous ranks for trend calculation
function getPreviousRanks($pdo, $timeframe) {
    // For simplicity, use cached data from previous generation
    // In production, you might want a separate history table
    return [];
}

// Get user badges
function getUserBadges($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT badge_key, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Get overall statistics (with optional timeframe filter, excludes shadow banned users)
function getOverallStats($pdo, $timeframe = 'alltime') {
    $stats = [];
    
    $dateFilter = '';
    switch ($timeframe) {
        case 'weekly':
            $dateFilter = 'WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.is_shadow_banned = 0';
            break;
        case 'monthly':
            $dateFilter = 'WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND u.is_shadow_banned = 0';
            break;
        default:
            $dateFilter = 'WHERE u.is_shadow_banned = 0';
    }
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM comments c INNER JOIN users u ON c.user_id = u.id ' . $dateFilter);
    $row = $stmt->fetch();
    $stats['total_comments'] = (int)$row['count'];
    
    $stmt = $pdo->query('SELECT COUNT(DISTINCT c.user_id) as count FROM comments c INNER JOIN users u ON c.user_id = u.id ' . $dateFilter);
    $row = $stmt->fetch();
    $stats['total_commenters'] = (int)$row['count'];
    
    // Submissions don't have timeframe filter, keep as is
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM submissions');
    $row = $stmt->fetch();
    $stats['total_submissions'] = (int)$row['count'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $row = $stmt->fetch();
    $stats['total_users'] = (int)$row['count'];
    
    return $stats;
}

// Get personal rank
function getPersonalRank($pdo, $telegramId, $timeframe = 'alltime') {
    $dateFilter = '';
    switch ($timeframe) {
        case 'weekly':
            $dateFilter = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'monthly':
            $dateFilter = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
    }
    
    // Get user's comment count (use INNER JOIN since we only care about users with comments)
    $stmt = $pdo->prepare("
        SELECT u.id as user_id, COUNT(c.id) as comment_count
        FROM users u
        INNER JOIN comments c ON u.id = c.user_id
        WHERE u.telegram_id = ? $dateFilter
        GROUP BY u.id
    ");
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch();
    
    if (!$user || $user['comment_count'] == 0) {
        return null;
    }
    
    // Get rank (COUNT(*) + 1 returns 1 if no one has more comments = rank #1)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM (
            SELECT COUNT(c2.id) as cnt
            FROM comments c2
            INNER JOIN users u2 ON c2.user_id = u2.id
            WHERE u2.is_shadow_banned = 0 $dateFilter
            GROUP BY u2.id
            HAVING cnt > ?
        ) as higher
    ");
    $stmt->execute([$user['comment_count']]);
    $row = $stmt->fetch();
    $rank = $row ? (int)$row['rank'] : 1; // Default to 1 if query fails
    
    // Get velocity (use timeframe-appropriate calculation)
    $daysInTimeframe = ($timeframe === 'weekly') ? 7 : (($timeframe === 'monthly') ? 30 : calculateDaysSinceFirstComment($pdo, $user['user_id']));
    $velocity = round($user['comment_count'] / max(1, $daysInTimeframe) * 30, 1);
    
    // Get most active hour (with timeframe filter, excludes shadow banned)
    $stmt = $pdo->prepare("
        SELECT HOUR(c.created_at) as hour, COUNT(*) as count
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.user_id = ? $dateFilter
        GROUP BY HOUR(c.created_at)
        ORDER BY count DESC
        LIMIT 1
    ");
    $stmt->execute([$user['user_id']]);
    $activeHour = $stmt->fetch();
    
    return [
        'rank' => $rank,
        'comment_count' => (int)$user['comment_count'],
        'comment_velocity' => $velocity,
        'most_active_hour' => (int)($activeHour['hour'] ?? 0)
    ];
}

// Calculate days since first comment
function calculateDaysSinceFirstComment($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT MIN(created_at) as first_comment FROM comments WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    
    if (!$row || !$row['first_comment']) {
        return 1;
    }
    
    $first = new DateTime($row['first_comment']);
    $now = new DateTime();
    return max(1, $first->diff($now)->days);
}

// Get user profile data
function getUserProfile($pdo, $telegramId) {
    // Get user info
    $stmt = $pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return null;
    }
    
    // Defensive: ensure required fields exist
    $user['username'] = $user['username'] ?? null;
    $user['first_name'] = $user['first_name'] ?? 'User';
    $user['first_seen'] = $user['first_seen'] ?? date('Y-m-d H:i:s');
    
    // Get comment stats
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM comments WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    $totalComments = (int)$row['total'];
    
    // Get velocity
    $velocity = round($totalComments / max(1, calculateDaysSinceFirstComment($pdo, $user['id'])), 1);
    
    // Get most active hours
    $stmt = $pdo->prepare('
        SELECT HOUR(created_at) as hour, COUNT(*) as count
        FROM comments
        WHERE user_id = ?
        GROUP BY HOUR(created_at)
        ORDER BY count DESC
    ');
    $stmt->execute([$user['id']]);
    $activeHours = $stmt->fetchAll();
    
    // Get badges
    $badges = getUserBadges($pdo, $user['id']);
    
    // Get titles
    $titles = getUserTitlesWithInfo($pdo, $user['id']);
    
    // Get streak
    $streak = calculateStreak($pdo, $user['id']);
    
    // Get comment history (last 50)
    $stmt = $pdo->prepare('
        SELECT c.comment_text, c.created_at, s.message_text as submission_text
        FROM comments c
        LEFT JOIN submissions s ON c.submission_id = s.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ');
    $stmt->execute([$user['id']]);
    $commentHistory = $stmt->fetchAll();
    
    // Get daily activity (last 30 days)
    $stmt = $pdo->prepare('
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM comments
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ');
    $stmt->execute([$user['id']]);
    $dailyActivity = $stmt->fetchAll();
    
    return [
        'user' => [
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'first_seen' => $user['first_seen'],
        ],
        'stats' => [
            'total_comments' => $totalComments,
            'comment_velocity' => $velocity,
            'streak' => $streak,
            'most_active_hours' => $activeHours,
        ],
        'badges' => $badges,
        'titles' => $titles,
        'comment_history' => $commentHistory,
        'daily_activity' => $dailyActivity,
    ];
}

// Get admin stats
function getAdminStats($pdo) {
    $stats = [];
    
    // Total users
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $row = $stmt->fetch();
    $stats['total_users'] = (int)$row['count'];
    
    // Shadow banned users
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE is_shadow_banned = 1');
    $row = $stmt->fetch();
    $stats['shadow_banned_users'] = (int)$row['count'];
    
    // Total comments
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM comments');
    $row = $stmt->fetch();
    $stats['total_comments'] = (int)$row['count'];
    
    // Total submissions
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM submissions');
    $row = $stmt->fetch();
    $stats['total_submissions'] = (int)$row['count'];
    
    // Comments today
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM comments WHERE DATE(created_at) = CURDATE()');
    $row = $stmt->fetch();
    $stats['comments_today'] = (int)$row['count'];
    
    // Cache status
    $stmt = $pdo->query('SELECT timeframe, generated_at FROM leaderboard_cache');
    $stats['cache_status'] = $stmt->fetchAll();
    
    // Recent activity
    $stmt = $pdo->query('
        SELECT ch.action, u.username, c.comment_text, ch.created_at
        FROM comment_history ch
        INNER JOIN users u ON ch.user_id = u.id
        INNER JOIN comments c ON ch.comment_id = c.id
        ORDER BY ch.created_at DESC
        LIMIT 20
    ');
    $stats['recent_activity'] = $stmt->fetchAll();
    
    return $stats;
}

// Rebuild all leaderboard caches
function rebuildAllCaches($pdo) {
    $timeframes = ['alltime', 'weekly', 'monthly'];
    $results = [];
    
    foreach ($timeframes as $timeframe) {
        try {
            $data = rebuildLeaderboardCache($pdo, $timeframe);
            $results[$timeframe] = 'success';
        } catch (Exception $e) {
            $results[$timeframe] = 'error: ' . $e->getMessage();
        }
    }
    
    return $results;
}

// ============================================
// AUTO TITLE SYSTEM FUNCTIONS
// ============================================

/**
 * Check and update titles for a user after comment
 * Titles are dynamic and can be gained/lost based on statistics
 */
function checkAndUpdateTitles($pdo, $userId, $telegramId = null) {
    $eligibleTitles = calculateTitleEligibility($pdo, $userId);
    $currentTitles = getUserCurrentTitles($pdo, $userId);
    
    // Titles to add
    $newTitles = array_diff($eligibleTitles, $currentTitles);
    // Titles to remove (no longer eligible)
    $removedTitles = array_diff($currentTitles, $eligibleTitles);
    
    // Add new titles
    foreach ($newTitles as $titleKey) {
        addTitle($pdo, $userId, $titleKey);
    }
    
    // Remove titles that are no longer eligible
    foreach ($removedTitles as $titleKey) {
        removeTitle($pdo, $userId, $titleKey);
    }
    
    return [
        'added' => $newTitles,
        'removed' => $removedTitles,
        'current' => calculateTitleEligibility($pdo, $userId)
    ];
}

/**
 * Calculate which titles a user is eligible for
 */
function calculateTitleEligibility($pdo, $userId) {
    $eligibleTitles = [];
    
    // Get user stats
    $stats = getUserCommentStats($pdo, $userId);
    
    if (!$stats || $stats['total_comments'] < 1) {
        return $eligibleTitles;
    }
    
    // Night Warrior: 50%+ comments between 23:00-04:00
    if ($stats['total_comments'] >= 10) {
        $nightCount = getCommentsInHourRange($pdo, $userId, 23, 4);
        if ($stats['total_comments'] > 0 && ($nightCount / $stats['total_comments']) >= 0.5) {
            $eligibleTitles[] = 'night_warrior';
        }
    }
    
    // Early Bird: 50%+ comments between 05:00-08:00
    if ($stats['total_comments'] >= 10) {
        $earlyCount = getCommentsInHourRange($pdo, $userId, 5, 8);
        if ($stats['total_comments'] > 0 && ($earlyCount / $stats['total_comments']) >= 0.5) {
            $eligibleTitles[] = 'early_bird';
        }
    }
    
    // Philosopher: Average comment length > 200 characters
    if ($stats['total_comments'] >= 5 && $stats['avg_length'] > 200) {
        $eligibleTitles[] = 'philosopher';
    }
    
    // Social Butterfly: Commented on 10+ different threads
    if ($stats['unique_threads'] >= 10) {
        $eligibleTitles[] = 'social_butterfly';
    }
    
    // Speed Demon: 5+ comments within 1 hour (checked via recent burst)
    if (hasCommentBurst($pdo, $userId, 5, 3600)) {
        $eligibleTitles[] = 'speed_demon';
    }
    
    // Hot Streak: 14+ consecutive days
    if ($stats['streak'] >= 14) {
        $eligibleTitles[] = 'hot_streak';
    }
    
    // Deep Thinker: 50+ comments with avg length > 150 characters
    if ($stats['total_comments'] >= 50 && $stats['avg_length'] > 150) {
        $eligibleTitles[] = 'deep_thinker';
    }
    
    // Trending: Highest velocity this week
    if (isWeeklyTopCommenter($pdo, $userId)) {
        $eligibleTitles[] = 'trending';
    }
    
    // Focused: 80%+ comments in top 3 threads
    if ($stats['total_comments'] >= 10 && isFocusedCommenter($pdo, $userId)) {
        $eligibleTitles[] = 'focused';
    }
    
    // Analyst: Commented on 25+ different threads
    if ($stats['unique_threads'] >= 25) {
        $eligibleTitles[] = 'analyst';
    }
    
    return $eligibleTitles;
}

/**
 * Get user's comment statistics
 */
function getUserCommentStats($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_comments,
            AVG(LENGTH(comment_text)) as avg_length,
            COUNT(DISTINCT submission_id) as unique_threads,
            MIN(created_at) as first_comment,
            MAX(created_at) as last_comment
        FROM comments 
        WHERE user_id = ?
    ');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    
    if (!$row || $row['total_comments'] == 0) {
        return null;
    }
    
    // Calculate streak
    $streak = calculateStreak($pdo, $userId);
    
    return [
        'total_comments' => (int)$row['total_comments'],
        'avg_length' => (float)$row['avg_length'],
        'unique_threads' => (int)$row['unique_threads'],
        'first_comment' => $row['first_comment'],
        'last_comment' => $row['last_comment'],
        'streak' => $streak
    ];
}

/**
 * Count comments in a specific hour range
 */
function getCommentsInHourRange($pdo, $userId, $startHour, $endHour) {
    if ($startHour > $endHour) {
        // Range crosses midnight (e.g., 23:00-04:00)
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM comments 
            WHERE user_id = ? 
            AND (HOUR(created_at) >= ? OR HOUR(created_at) <= ?)
        ');
        $stmt->execute([$userId, $startHour, $endHour]);
    } else {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM comments 
            WHERE user_id = ? 
            AND HOUR(created_at) BETWEEN ? AND ?
        ');
        $stmt->execute([$userId, $startHour, $endHour]);
    }
    
    $row = $stmt->fetch();
    return $row ? (int)$row['count'] : 0;
}

/**
 * Check if user has a burst of N comments within X seconds
 */
function hasCommentBurst($pdo, $userId, $minComments, $timeWindow) {
    // Validate inputs
    if ($minComments < 2 || $timeWindow < 1) {
        return false;
    }
    
    // Get recent comments
    $stmt = $pdo->prepare('
        SELECT created_at FROM comments 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ');
    $stmt->execute([$userId]);
    $comments = $stmt->fetchAll();
    
    if (count($comments) < $minComments) {
        return false;
    }
    
    // Check for any window with burst
    for ($i = 0; $i <= count($comments) - $minComments; $i++) {
        $startTime = strtotime($comments[$i]['created_at']);
        $endTime = strtotime($comments[$i + $minComments - 1]['created_at']);
        
        // Validate timestamps
        if ($startTime === false || $endTime === false) {
            continue;
        }
        
        if (($startTime - $endTime) <= $timeWindow) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user is the top commenter this week
 */
function isWeeklyTopCommenter($pdo, $userId) {
    // Get current user's weekly count
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM comments 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ');
    $stmt->execute([$userId]);
    $userCount = $stmt->fetch();
    
    if (!$userCount || $userCount['count'] < 5) {
        return false;
    }
    
    // Check if anyone has more
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        WHERE u.is_shadow_banned = 0 
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND c.user_id != ?
        GROUP BY c.user_id
        HAVING count > ?
        LIMIT 1
    ');
    $stmt->execute([$userId, $userCount['count']]);
    
    return !$stmt->fetch(); // True if no one has more
}

/**
 * Check if user is a focused commenter (80%+ in top 3 threads)
 */
function isFocusedCommenter($pdo, $userId) {
    // Get total comments
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM comments WHERE user_id = ?');
    $stmt->execute([$userId]);
    $totalRow = $stmt->fetch();
    
    if (!$totalRow || $totalRow['total'] < 10) {
        return false;
    }
    
    // Get top 3 threads by comment count
    $stmt = $pdo->prepare('
        SELECT submission_id, COUNT(*) as count 
        FROM comments 
        WHERE user_id = ? 
        GROUP BY submission_id 
        ORDER BY count DESC 
        LIMIT 3
    ');
    $stmt->execute([$userId]);
    $topThreads = $stmt->fetchAll();
    
    $topCount = 0;
    foreach ($topThreads as $thread) {
        $topCount += $thread['count'];
    }
    
    $percentage = ($topCount / $totalRow['total']) * 100;
    return $percentage >= 80;
}

/**
 * Get user's current titles
 */
function getUserCurrentTitles($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT title_key FROM user_titles WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'title_key');
}

/**
 * Get user titles with display info
 */
function getUserTitlesWithInfo($pdo, $userId) {
    $titles = getUserCurrentTitles($pdo, $userId);
    $titlesWithInfo = [];
    
    foreach ($titles as $titleKey) {
        if (isset(AUTO_TITLES[$titleKey])) {
            $titlesWithInfo[] = [
                'key' => $titleKey,
                'name' => AUTO_TITLES[$titleKey]['name'],
                'icon' => AUTO_TITLES[$titleKey]['icon'],
                'description' => AUTO_TITLES[$titleKey]['description']
            ];
        }
    }
    
    return $titlesWithInfo;
}

/**
 * Add a title to a user
 */
function addTitle($pdo, $userId, $titleKey) {
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_titles (user_id, title_key) VALUES (?, ?)');
    $stmt->execute([$userId, $titleKey]);
}

/**
 * Remove a title from a user
 */
function removeTitle($pdo, $userId, $titleKey) {
    $stmt = $pdo->prepare('DELETE FROM user_titles WHERE user_id = ? AND title_key = ?');
    $stmt->execute([$userId, $titleKey]);
}
