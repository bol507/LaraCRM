<?php

namespace App\Services;

use App\Application\Contracts\ActivityTrackerInterface;

class ActivityTrackerWrapper implements ActivityTrackerInterface
{
    public function created(string $module, int $crmid, ?int $userId = null): int
    {
        return VtigerActivityTracker::created($module, $crmid, $userId);
    }

    public function updated(string $module, int $crmid, ?int $userId = null): int
    {
        return VtigerActivityTracker::updated($module, $crmid, $userId);
    }

    public function deleted(string $module, int $crmid, ?int $userId = null): int
    {
        return VtigerActivityTracker::deleted($module, $crmid, $userId);
    }

    public function restored(string $module, int $crmid, ?int $userId = null): int
    {
        return VtigerActivityTracker::restored($module, $crmid, $userId);
    }

    public function transferred(string $module, int $crmid, ?int $userId = null): int
    {
        return VtigerActivityTracker::transferred($module, $crmid, $userId);
    }

    public function addComment(int $crmid, string $comment, ?int $userId = null): int
    {
        return VtigerActivityTracker::addComment($crmid, $comment, $userId);
    }
}