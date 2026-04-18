<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\CreateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Traits\GeneratesLockNames;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateUserUseCase
{
    use GeneratesLockNames;

    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly IdGeneratorRepository $idGenerator
    ) {}

    /**
     * Execute user creation flow.
     *
     * @throws InvalidArgumentException If validation rules are violated
     * @throws RuntimeException If persistence fails
     */
    public function execute(CreateUserRequest $request, int $authenticatedUserId): User
    {
        return DB::connection('vtiger')->transaction(function () use ($request, $authenticatedUserId) {

            $now = now()->format('Y-m-d H:i:s');
            $hashedPwd = password_hash($request->password, PASSWORD_DEFAULT);
            $lockName = $this->getIdGenerationLockName(
                table: 'vtiger_users',
                context: $authenticatedUserId
            );
            $newId = $this->idGenerator->generateNextId(
                table: 'vtiger_users',
                column: 'id',
                lockName: $lockName
            );


            
            DB::connection('vtiger')->table('vtiger_users')->insert([
                'id'                    => $newId,
                'user_name'             => $request->user_name,
                'first_name'            => $request->first_name,
                'last_name'             => $request->last_name,
                'email1'                => $request->email,
                'is_admin'              => $request->is_admin ? '1' : '0',
                'status'                => $request->status,
                'user_password'         => $hashedPwd,
                'confirm_password'      => $hashedPwd,
                'crypt_type'            => 'PHASH',
                'phone_crm_extension'   => $request->phone_crm,
                'department'            => $request->department,
                'reports_to_id'         => $request->reports_to_id,
                'date_entered'          => $now,
                'date_modified'         => $now,
                'modified_user_id'      => $authenticatedUserId,
                'deleted'               => 0,
                'internal_mailer'       => 1,
            ]);

            
            if ($request->role_id) {
                DB::connection('vtiger')->table('vtiger_user2role')->updateOrInsert(
                    ['userid' => $newId],
                    ['roleid' => $request->role_id]
                );
            }

            
            return $this->repository->findById($newId)
                ?? throw new RuntimeException('Failed to retrieve created user');
        });
    }
}
