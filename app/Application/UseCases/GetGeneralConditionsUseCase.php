<?php

namespace App\Application\UseCases;

use App\Application\Repositories\GeneralConditionsRepositoryInterface;

class GetGeneralConditionsUseCase
{
    protected $repository;

    public function __construct(GeneralConditionsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * execute the use case
     */
    public function execute(string $type): ?string
    {
        return $this->repository->getConditionsByType($type);
    }
}