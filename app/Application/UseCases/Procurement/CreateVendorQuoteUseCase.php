<?php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\CreateVendorQuoteDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;

// app/Application/UseCases/Procurement/CreateVendorQuoteUseCase.php
class CreateVendorQuoteUseCase {
    public function __construct(
        private readonly VendorQuoteRepositoryInterface $repo,
        private readonly MaterialRequestRepositoryInterface $requestRepo,
    ) {}

    public function execute(CreateVendorQuoteDto $dto): int {
        return DB::connection('vtiger')->transaction(function () use ($dto) {
            $quoteId = $this->repo->create([
                'project_id' => $dto->projectId,
                'material_request_id' => $dto->materialRequestId,
                'vendor_id' => $dto->vendorId,
                'quote_number' => $this->generateQuoteNumber($dto->projectId),
                'status' => 'draft',
                'valid_until' => $dto->validUntil,
                'terms' => $dto->terms,
                'notes' => $dto->notes,
                'created_by' => $dto->createdById,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($dto->items as $item) {
                $this->repo->createItem($quoteId, $item);
            }

            $this->repo->updateStatus($quoteId, 'sent');
            $this->requestRepo->updateStatus(
                id: $dto->materialRequestId,
                status: 'procurement_in_progress'
              
            );
            return $quoteId;
        });
    }

    private function generateQuoteNumber(int $projectId): string {
        $year = date('Y');
        $prefix = "CF-{$year}-";
        $last = DB::connection('vtiger')->table('nova_vendor_quotes')
            ->where('quote_number', 'like', "{$prefix}%")->max('id');
        return $prefix . str_pad(($last ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    }
}