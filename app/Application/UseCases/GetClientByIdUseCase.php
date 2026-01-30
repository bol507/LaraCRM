<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class GetClientByIdUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(int $id): Client
    {
        $client = $this->clientRepository->findById($id);
        
        if (!$client) {
            throw new HttpResponseException(
                response()->json(['error' => 'Cliente no encontrado'], Response::HTTP_NOT_FOUND)
            );
        }

        return $client;
    }
}