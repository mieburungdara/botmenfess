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

-- Seed default tiers
INSERT INTO tiers (name, daily_limit, description, price) VALUES
('free', 3, 'Free tier - 3 submissions per day', 0.00),
('silver', 10, 'Silver tier - 10 submissions per day', 15000.00),
('gold', 30, 'Gold tier - 30 submissions per day', 30000.00),
('unlimited', -1, 'Unlimited tier - No daily limit', 50000.00);
