<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateUserRequest;
use App\Application\DTOs\UpdateUserProfileRequest;
use App\Domain\Entities\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator;
    public function findById(int $id): ?User;
    public function create(CreateUserRequest $request, int $createdByUserId): int;
    public function updateProfile(UpdateUserProfileRequest $request, int $modifiedByUserId): bool;
    public function delete(int $id, int $deletedByUserId): bool;
    
 
    public function getAvailableRoles(): array;
    public function changePassword(int $userId, string $newPassword, int $modifiedByUserId): bool;

    public function findByFullName(string $fullName): ?array;
    public function findByNameOrUsername(string $searchTerm): array;
}