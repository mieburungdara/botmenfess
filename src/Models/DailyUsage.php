<?php

namespace App\Models;

class DailyUsage {
    private $db;
    private $table = 'daily_usage';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTodayCount($uid) {
        $sql = 'SELECT count FROM ' . $this->table . ' du INNER JOIN users u ON du.user_id = u.id WHERE u.telegram_id = ? AND du.usage_date = CURDATE()';
        $s = $this->db->prepare($sql);
        $s->execute([$uid]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r ? (int)$r['count'] : 0;
    }

    public function increment($uid) {
        $sql = 'INSERT INTO ' . $this->table . ' (user_id, usage_date, count) SELECT id, CURDATE(), 1 FROM users WHERE telegram_id = ? ON DUPLICATE KEY UPDATE count = count + 1';
        $s = $this->db->prepare($sql);
        return $s->execute([$uid]);
    }

    public function getRemaining($uid, $lim) {
        if ($lim == -1) return -1;
        $used = $this->getTodayCount($uid);
        return max(0, $lim - $used);
    }
}