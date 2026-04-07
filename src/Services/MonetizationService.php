<?php

namespace App\Services;

use App\Models\Tier;
use App\Models\UserTier;
use App\Models\DailyUsage;

class MonetizationService {
    private $tierModel;
    private $userTierModel;
    private $dailyUsageModel;

    public function __construct($db) {
        $this->tierModel = new Tier($db);
        $this->userTierModel = new UserTier($db);
        $this->dailyUsageModel = new DailyUsage($db);
    }

    public function canSubmit($userId) {
        $limit = $this->userTierModel->getDailyLimit($userId);
        $rem = $this->dailyUsageModel->getRemaining($userId, $limit);
        if ($limit == -1) {
            return [true, "Unlimited - submit freely!", -1];
        }
        if ($rem <= 0) {
            $msg = "Limit harian habis (" . $limit . " menfess/hari). Upgrade tier!";
            return [false, $msg, 0];
        }
        return [true, "Sisa " . $rem . " menfess hari ini", $rem];
    }

    public function recordSubmission($uid) {
        $this->dailyUsageModel->increment($uid);
    }

    public function getUserStatus($uid) {
        $tier = $this->userTierModel->getCurrentTier($uid);
        $lim = $this->userTierModel->getDailyLimit($uid);
        $rem = $this->dailyUsageModel->getRemaining($uid, $lim);
        return [
            'tier' => $tier,
            'daily_limit' => $lim,
            'remaining' => $rem,
        ];
    }

    public function getAllTiers() {
        return $this->tierModel->getAllTiers();
    }
}