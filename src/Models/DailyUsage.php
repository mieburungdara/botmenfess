<?php

namespace App\Models;

class DailyUsage {
    private $db;
    private $table = 'daily_usage';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTodayCount($uid) {
        $today = date('Y-m-d');
        $sql = 'SELECT count FROM ' . $this->table . ' WHERE user_id = ? AND usage_date = ?';
        $s = $this->db->prepare($sql);
        $s->execute([$uid, $today]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r ? (int)$r['count'] : 0;
    }

    public function increment($uid) {
        $today = date('Y-m-d');
        $sql = 'INSERT INTO ' . $this->table . ' (user_id, usage_date, count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE count = count + 1';
        $s = $this->db->prepare($sql);
        return $s->execute([$uid, $today]);
    }

    public function getRemaining($uid, $lim) {
        if ($lim == -1) return -1;
        $used = $this->getTodayCount($uid);
        return max(0, $lim - $used);
    }
}