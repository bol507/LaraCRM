<?php

namespace App\Application\Repositories;

interface GeneralConditionsRepositoryInterface
{
    /**
     *  get conditions by type
     */
    public function getConditionsByType(string $type): ?string;
}