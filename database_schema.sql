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

-- submissions table: stores incoming messages from users
-- Note: No user identifiers stored here to maintain anonymity
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_text TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    moderator_notes TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    reviewed_by VARCHAR(255) NULL
) ENGINE=InnoDB;

-- channel_posts table: tracks which submissions have been posted to the channel
CREATE TABLE IF NOT EXISTS channel_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    telegram_message_id BIGINT NOT NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes for performance as mentioned in the plan
CREATE INDEX idx_submissions_status ON submissions(status);
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);