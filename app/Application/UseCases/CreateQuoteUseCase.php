<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\Repositories\QuoteRepositoryInterface;

class CreateQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    public function execute(CreateQuoteRequest $request, int $createdByUserId): int
    {
        // Validar que al menos haya un item
        if (empty($request->items)) {
            throw new \InvalidArgumentException('La cotización debe tener al menos un ítem');
        }

        return $this->quoteRepository->create($request, $createdByUserId);
    }
}