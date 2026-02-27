<?php

namespace App\Application\UseCases\User;

use App\Application\Repositories\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllUsersUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->userRepository->getAll($page, $perPage, $search);
    }
}