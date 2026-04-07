<?php

namespace App\Models;

class UserTier {
    private $db;
    private $table = 'user_tiers';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getCurrentTier($uid) {
        $sql = 'SELECT t.* FROM tiers t INNER JOIN ' . $this->table . ' ut ON t.id = ut.tier_id INNER JOIN users u ON ut.user_id = u.id WHERE u.telegram_id = ? AND ut.is_active = 1 AND (ut.expires_at IS NULL OR ut.expires_at > NOW()) ORDER BY ut.started_at DESC LIMIT 1';
        $s = $this->db->prepare($sql);
        $s->execute([$uid]);
        return $s->fetch(\PDO::FETCH_ASSOC);
    }

    public function getTierId($uid) {
        $t = $this->getCurrentTier($uid);
        return $t ? (int)$t['id'] : 1;
    }

    public function getDailyLimit($uid) {
        $t = $this->getCurrentTier($uid);
        return $t ? (int)$t['daily_limit'] : 3;
    }

    // Note: This method expects a telegram_id (like other methods), but internally converts to user_id
    public function assign($telegramId, $tierId, $exp=null) {
        // First get the user_id from telegram_id
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        $this->deactivate($telegramId);
        $sql = 'INSERT INTO ' . $this->table . ' (user_id, tier_id, expires_at, is_active) VALUES (?, ?, ?, 1)';
        $s = $this->db->prepare($sql);
        return $s->execute([$user['id'], $tierId, $exp]);
    }

    // Note: This method expects a telegram_id but the column is user_id, so we convert
    public function deactivate($telegramId) {
        // First get the user_id from telegram_id
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        $sql = 'UPDATE ' . $this->table . ' SET is_active = 0 WHERE user_id = ?';
        $s = $this->db->prepare($sql);
        return $s->execute([$user['id']]);
    }
}