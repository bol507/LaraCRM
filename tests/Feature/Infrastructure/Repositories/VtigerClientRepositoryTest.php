<?php

namespace Tests\Feature\Infrastructure\Repositories;

use App\Domain\Entities\Client;
use App\Infrastructure\Repositories\VtigerClientRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\VtigerDatabaseTrait;

class VtigerClientRepositoryTest extends TestCase
{
    //use RefreshDatabase;
    use VtigerDatabaseTrait;

    protected VtigerClientRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VtigerClientRepository();
    }

    /**
     * @group integration
     */
    public function test_get_all_returns_active_clients_from_vtiger()
    {
        // Arrange: Insertar datos en la base de datos de Vtiger
        DB::connection('vtiger')->table('vtiger_account')->insert([
            [
                'accountid' => 1,
                'accountname' => 'Cliente Activo',
                'email1' => 'activo@cliente.com',
                'phone' => '1234-5678',
                'deleted' => 0
            ],
            [
                'accountid' => 2,
                'accountname' => 'Cliente Eliminado',
                'email1' => 'eliminado@cliente.com',
                'phone' => '8765-4321',
                'deleted' => 1 // ¡Este no debe aparecer!
            ]
        ]);

        // Act
        $paginator = $this->repository->getAll(1, 10);

        // Assert
        $this->assertCount(1, $paginator->items());
        $this->assertEquals('Cliente Activo', $paginator->items()[0]->name);
        $this->assertEquals('activo@cliente.com', $paginator->items()[0]->email);
    }

    /**
     * @group integration
     */
    public function test_find_by_id_returns_client_when_exists()
    {
        // Arrange
        DB::connection('vtiger')->table('vtiger_account')->insert([
            'accountid' => 3,
            'accountname' => 'Cliente Único',
            'email1' => 'unico@cliente.com',
            'phone' => '1111-2222',
            'deleted' => 0
        ]);

        // Act
        $client = $this->repository->findById(3);

        // Assert
        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('Cliente Único', $client->name);
    }

    /**
     * @group integration
     */
    public function test_find_by_id_returns_null_when_client_does_not_exist()
    {
        $client = $this->repository->findById(999);
        $this->assertNull($client);
    }
}