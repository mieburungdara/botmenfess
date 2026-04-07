<?php

namespace App\Models;

class Tier {
    private $db;
    private $table = 'tiers';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAllTiers()
    {
        $stmt = $this->db->query('SELECT * FROM ' . $this->table . ' ORDER BY CASE WHEN daily_limit = -1 THEN 999999 ELSE daily_limit END ASC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTierById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getTierByName($name)
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getDailyLimit($tierId)
    {
        $tier = $this->getTierById($tierId);
        return $tier ? (int)$tier['daily_limit'] : 3;
    }
}