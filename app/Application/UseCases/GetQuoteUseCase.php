<?php

namespace App\Application\UseCases;

use App\Application\Repositories\QuoteRepositoryInterface;
use App\Application\DTOs\QuoteResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class GetQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    public function executeById(int $quoteid): ?QuoteResponse
    {
        return $this->quoteRepository->findById($quoteid);
    }

    public function execute(int $page, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        return $this->quoteRepository->paginate($page, $perPage, $search);
    }
}