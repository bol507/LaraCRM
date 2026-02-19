<?php

namespace App\Application\DTOs;

use App\Domain\Entities\Opportunity;

class OpportunityDto
{
    public static function fromEntity(Opportunity $opportunity): array
    {
        return [
            'potentialid' => $opportunity->potentialid,
            'potential_no' => $opportunity->potential_no,
            'potentialname' => $opportunity->potentialname,
            'amount' => $opportunity->amount,
            'closingdate' => $opportunity->closingdate,
            'sales_stage' => $opportunity->sales_stage,
            'probability' => $opportunity->probability,
            'related_to' => $opportunity->related_to,
            'related_to_name' => $opportunity->related_to_name,
            'assigned_user_id' => $opportunity->assigned_user_id,
            'assigned_user_name' => $opportunity->assigned_user_name,
            'description' => $opportunity->description,
            'is_active' => $opportunity->is_active,
        ];
    }
}