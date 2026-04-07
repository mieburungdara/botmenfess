-- Database schema for Menfess Telegram Bot
-- Using MySQL with InnoDB engine as specified in the plan

CREATE DATABASE IF NOT EXISTS menfess_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE menfess_bot;

-- users table: stores information about users who interact with the bot
-- Note: This table is NOT linked to submissions to maintain anonymity
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL UNIQUE,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    is_bot BOOLEAN DEFAULT FALSE,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submission_count INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_at TIMESTAMP NULL,
    blocked_by VARCHAR(255) NULL,
    UNIQUE KEY idx_telegram_id (telegram_id)
) ENGINE=InnoDB;

-- tiers table: defines the subscription tiers and their daily limits
CREATE TABLE IF NOT EXISTS tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    daily_limit INT NOT NULL DEFAULT 3, -- -1 means unlimited
    description TEXT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- user_tiers table: links users to their current tier
CREATE TABLE IF NOT EXISTS user_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES tiers(id) ON DELETE RESTRICT,
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB;

-- daily_usage table: tracks daily submissions per user
CREATE TABLE IF NOT EXISTS daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    count INT DEFAULT 0,
    UNIQUE KEY idx_user_date (user_id, usage_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- submissions table: stores incoming messages from users
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_text TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    moderator_notes TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    reviewed_by VARCHAR(255) NULL,
    anonymous_id VARCHAR(20) NULL
) ENGINE=InnoDB;

-- channel_posts table: tracks which submissions have been posted to the channel
CREATE TABLE IF NOT EXISTS channel_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    telegram_message_id BIGINT NOT NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes for performance
CREATE INDEX idx_submissions_status ON submissions(status);
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);
CREATE INDEX idx_submissions_status_submitted ON submissions(status, submitted_at);

-- ============================================
-- COMMENTS TABLE
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
    INDEX idx_comments_user_created (user_id, created_at),
    INDEX idx_comments_created (created_at),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- LEADERBOARD CACHE TABLE
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
-- USER BADGES TABLE
-- Stores earned badges for each user
-- ============================================
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_badge (user_id, badge_key),
    INDEX idx_user_badges_user_earned (user_id, earned_at)
) ENGINE=InnoDB;

-- ============================================
-- COMMENT HISTORY TABLE
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
-- USER TITLES TABLE
-- Stores auto-earned titles based on statistics (dynamic, can change)
-- ============================================
CREATE TABLE IF NOT EXISTS user_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_title (user_id, title_key),
    INDEX idx_user_titles_user_earned (user_id, earned_at)
) ENGINE=InnoDB;

-- ============================================
-- UPDATE USERS TABLE
-- Add shadow ban and notification tracking columns
-- ============================================
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS is_shadow_banned BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS shadow_banned_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS shadow_banned_by VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS last_badge_notification_sent VARCHAR(50) NULL;

-- Add indexes for shadow ban queries performance
CREATE INDEX idx_users_shadow_banned ON users(is_shadow_banned);
CREATE INDEX idx_users_telegram ON users(telegram_id);

-- Seed default tiers
INSERT INTO tiers (name, daily_limit, description, price) VALUES
('free', 3, 'Free tier - 3 submissions per day', 0.00),
('silver', 10, 'Silver tier - 10 submissions per day', 15000.00),
('gold', 30, 'Gold tier - 30 submissions per day', 30000.00),
('unlimited', -1, 'Unlimited tier - No daily limit', 50000.00);
