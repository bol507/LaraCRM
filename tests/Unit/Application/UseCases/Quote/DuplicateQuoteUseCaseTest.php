<?php

namespace Tests\Unit\Application\UseCases\Quote;

use App\Application\DTOs\Quote\QuoteResponse;
use App\Application\UseCases\Quote\DuplicateQuoteUseCase;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Services\VtigerActivityTracker;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use Mockery;

class DuplicateQuoteUseCaseTest extends TestCase
{
    private QuoteRepository $quoteRepository;
    private DuplicateQuoteUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock del repositorio
        $this->quoteRepository = Mockery::mock(QuoteRepository::class);
        
        // Instanciar el use case con el mock
        $this->useCase = new DuplicateQuoteUseCase($this->quoteRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();  // ✅ Limpiar mocks
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_when_quote_id_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quote ID must be positive');
        
        $this->useCase->execute(0);
    }

    /** @test */
    public function it_throws_exception_when_quote_not_found(): void
    {
        $quoteId = 999;
        
        $this->quoteRepository
            ->shouldReceive('findById')
            ->once()
            ->with($quoteId)
            ->andReturn(null);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Quote {$quoteId} not found");
        
        $this->useCase->execute($quoteId);
    }

    /** @test */
    public function it_duplicates_quote_with_all_data_and_items(): void
    {
        $originalQuoteId = 123;
        $newQuoteId = 456;
        $userId = 5;
        
        $originalQuote = new QuoteResponse(
            quoteid: $originalQuoteId,
            quoteno: 'Q20260001',
            subject: 'Original Quote',
            quote_stage: 'Sent',
            accountid: 89,
            assigned_user_id: 10,
            validtill: '2026-12-31',
            subtotal: 1000.00,
            total: 1070.00,
            createdtime: '2026-03-01 10:00:00',
            modifiedtime: '2026-03-01 10:00:00',
            items: [
                [
                    'productid' => 1,
                    'sequence_no' => 1,
                    'productname' => 'Product A',
                    'quantity' => 2,
                    'listprice' => 500.00,
                    'discount_percent' => 0,
                    'netprice' => 500.00,
                    'total' => 1000.00,
                    'description' => 'Description A',
                ],
            ],
            account_name: 'Test Client',
            assigned_user_name: 'Test User',
            isActive: true,
        );
        
        // Configurar mocks con Mockery
        $this->quoteRepository
            ->shouldReceive('findById')
            ->once()
            ->with($originalQuoteId)
            ->andReturn($originalQuote);
        
        $this->quoteRepository
            ->shouldReceive('generateQuoteId')
            ->once()
            ->andReturn($newQuoteId);
        
        $this->quoteRepository
            ->shouldReceive('insert')
            ->once()
            ->with(
                Mockery::on(fn ($data) => $data['quoteid'] === $newQuoteId),
                Mockery::on(fn ($items) => count($items) === 1)
            )
            ->andReturn($newQuoteId);
        
        // ✅ Mock para VtigerActivityTracker (métodos estáticos)
        VtigerActivityTracker::shouldReceive('created')
            ->once()
            ->with('Quotes', $newQuoteId, $userId)
            ->andReturn(1);
        
        VtigerActivityTracker::shouldReceive('addComment')
            ->once()
            ->with($originalQuoteId, Mockery::any(), $userId)
            ->andReturn(1);
        
        // Ejecutar el use case
        $result = $this->useCase->execute($originalQuoteId, $userId);
        
        $this->assertEquals($newQuoteId, $result);
    }

    /** @test */
    public function it_duplicates_quote_without_items(): void
    {
        $originalQuoteId = 123;
        $newQuoteId = 456;
        $userId = 5;
        
        $originalQuote = $this->createMinimalQuote($originalQuoteId);
        
        $this->quoteRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($originalQuote);
        
        $this->quoteRepository
            ->shouldReceive('generateQuoteId')
            ->once()
            ->andReturn($newQuoteId);
        
        $this->quoteRepository
            ->shouldReceive('insert')
            ->once()
            ->with(
                Mockery::on(fn ($data) => $data['quoteid'] === $newQuoteId),
                []
            )
            ->andReturn($newQuoteId);
        
        VtigerActivityTracker::shouldReceive('created')
            ->once()
            ->andReturn(1);
        
        VtigerActivityTracker::shouldReceive('addComment')
            ->once()
            ->andReturn(1);
        
        $result = $this->useCase->execute($originalQuoteId, $userId);
        
        $this->assertEquals($newQuoteId, $result);
    }

    private function createMinimalQuote(int $id, string $subject = 'Test Quote'): QuoteResponse
    {
        return new QuoteResponse(
            quoteid: $id,
            quoteno: 'Q20260001',
            subject: $subject,
            quote_stage: 'Draft',
            accountid: 89,
            assigned_user_id: 10,
            validtill: null,
            subtotal: null,
            total: null,
            createdtime: '2026-03-01 10:00:00',
            modifiedtime: '2026-03-01 10:00:00',
            items: [],
            account_name: 'Test Client',
            assigned_user_name: 'Test User',
            isActive: true,
        );
    }
}