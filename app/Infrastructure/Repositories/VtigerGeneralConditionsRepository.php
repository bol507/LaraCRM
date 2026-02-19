<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\GeneralConditionsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VtigerGeneralConditionsRepository implements GeneralConditionsRepositoryInterface
{
    /**
     *  get conditions by type
     */
    public function getConditionsByType(string $type): ?string
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_inventory_tandc')
                ->where('type', $type)
                ->value('tandc');
        } catch (\Exception $e) {
            Log::error('Error vtiger get conditions by type: ' . $e->getMessage());
            return null;
        }
    }
}