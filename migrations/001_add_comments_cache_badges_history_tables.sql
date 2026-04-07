-- Migration: 001_add_comments_cache_badges_history_tables.sql
-- Description: Add tables for comments, leaderboard cache, user badges, comment history, and shadow ban support

USE menfess_bot;

-- ============================================
-- 1. COMMENTS TABLE
-- Stores all comments from discussion group
-- ============================================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    telegram_message_id BIGINT NULL,
    reply_to_message_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_submission_id (submission_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 2. LEADERBOARD CACHE TABLE
-- Stores pre-computed leaderboard data (daily refresh)
-- ============================================
CREATE TABLE IF NOT EXISTS leaderboard_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timeframe VARCHAR(20) NOT NULL,
    cache_data JSON NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_timeframe (timeframe)
) ENGINE=InnoDB;

-- ============================================
-- 3. USER BADGES TABLE
-- Stores earned badges for each user
-- ============================================
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_badge (user_id, badge_key)
) ENGINE=InnoDB;

-- ============================================
-- 4. COMMENT HISTORY TABLE
-- Tracks comment actions for audit trail
-- ============================================
CREATE TABLE IF NOT EXISTS comment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================
-- 5. UPDATE USERS TABLE
-- Add shadow ban and notification tracking columns
-- ============================================
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS is_shadow_banned BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS shadow_banned_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS shadow_banned_by VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS last_badge_notification_sent VARCHAR(50) NULL;

-- ============================================
-- 6. SEED DEFAULT BADGES
-- ============================================
-- Note: Badges are defined in code, not stored in a separate table
-- The badge definitions are:
-- weekly_champion, monthly_legend, alltime_king, rising_star,
-- active_commenter, newcomer, early_bird, night_owl,
-- streak_master, conversation_starter

-- ============================================
-- 7. INDEXES FOR PERFORMANCE
-- ============================================
-- Already defined in table creation above
-- Additional indexes for complex queries:
CREATE INDEX idx_comments_user_created ON comments(user_id, created_at);
CREATE INDEX idx_comments_created ON comments(created_at);
CREATE INDEX idx_user_badges_user_earned ON user_badges(user_id, earned_at);

-- Migration complete
SELECT 'Migration 001 completed successfully' AS status;