<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\Repositories\QuoteRepositoryInterface;

class UpdateQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    public function execute(UpdateQuoteRequest $request, int $modifiedByUserId): bool
    {
        if (empty($request->items)) {
            throw new \InvalidArgumentException('La cotización debe tener al menos un ítem');
        }

        return $this->quoteRepository->update($request, $modifiedByUserId);
    }
}