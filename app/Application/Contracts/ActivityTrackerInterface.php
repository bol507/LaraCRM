<?php

namespace App\Application\Contracts;

/**
 * Contract for activity tracking operations.
 * Allows mocking for tests and swapping implementations.
 */
interface ActivityTrackerInterface
{
    public function created(string $module, int $crmid, ?int $userId = null): int;
    public function updated(string $module, int $crmid, ?int $userId = null): int;
    public function deleted(string $module, int $crmid, ?int $userId = null): int;
    public function restored(string $module, int $crmid, ?int $userId = null): int;
    public function addComment(int $crmid, string $comment, ?int $userId = null): int;
}