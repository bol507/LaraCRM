<?php

namespace App\Application\UseCases;

use App\Application\Repositories\QuoteRepositoryInterface;

class DeleteQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    public function execute(int $quoteid, int $deletedByUserId): bool
    {
        // El userId puede usarse para auditoría si lo necesitas
        return $this->quoteRepository->delete($quoteid);
    }
}