-- Migration: Add user_titles table for auto-earned titles
-- Date: 2026-04-08

CREATE TABLE IF NOT EXISTS user_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_title (user_id, title_key),
    INDEX idx_user_titles_user_earned (user_id, earned_at)
) ENGINE=InnoDB;