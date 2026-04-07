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

    public function assign($uid, $tid, $exp=null) {
        $this->deactivate($uid);
        $sql = 'INSERT INTO ' . $this->table . ' (user_id, tier_id, expires_at, is_active) VALUES (?, ?, ?, 1)';
        $s = $this->db->prepare($sql);
        return $s->execute([$uid, $tid, $exp]);
    }

    public function deactivate($uid) {
        $sql = 'UPDATE ' . $this->table . ' SET is_active = 0 WHERE user_id = ?';
        $s = $this->db->prepare($sql);
        return $s->execute([$uid]);
    }
}