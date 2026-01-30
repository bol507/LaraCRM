<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\UseCases\GetAllClientsUseCase;
use App\Domain\Entities\Client;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class GetAllClientsUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_execute_returns_paginated_clients()
    {
        
        $mockRepository = m::mock(ClientRepositoryInterface::class);
        
        $expectedClients = [
            new Client(1, 'Cliente A', 'a@cliente.com', '1234'),
            new Client(2, 'Cliente B', 'b@cliente.com', '5678')
        ];
        
        $paginator = new LengthAwarePaginator(
            $expectedClients,
            2,
            20,
            1
        );

        $mockRepository
            ->shouldReceive('getAll')
            ->with(1, 20)
            ->andReturn($paginator);

        // Act
        $useCase = new GetAllClientsUseCase($mockRepository);
        $result = $useCase->execute(1, 20);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertEquals('Cliente A', $result->items()[0]->name);
    }
}