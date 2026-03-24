<?php

namespace App\Observers\Traits;

use App\Services\VtigerActivityTracker;
use App\Services\CurrentUserService;
use Illuminate\Database\Eloquent\Model;

trait LogsToVtiger
{
    public static function bootLogsToVtiger(): void
    {
        static::created(fn(Model $model) => static::logToVtiger($model, 'created'));
        static::updated(fn(Model $model) => static::logToVtiger($model, 'updated'));
        static::deleted(fn(Model $model) => static::logToVtiger($model, 'deleted'));
    }

    protected static function logToVtiger(Model $model, string $action): void
    {
        if (!method_exists($model, 'getVtigerModule')) {
            return;
        }

        $status = match($action) {
            'created' => 0,
            'updated' => 1,
            'deleted' => 2,
            default => 1,
        };

        
        $userId = CurrentUserService::idOr(1);

        VtigerActivityTracker::log(
            module: $model->getVtigerModule(),
            crmid: $model->getKey(),
            status: $status,
            userId: $userId
        );
    }
}