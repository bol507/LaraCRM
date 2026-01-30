<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function test_client_can_be_created_with_valid_data()
    {
        $client = new Client(
            id: 1,
            name: 'Empresa ABC',
            email: 'contacto@empresaabc.com',
            phone: '+507 1234-5678'
        );

        $this->assertEquals(1, $client->id);
        $this->assertEquals('Empresa ABC', $client->name);
        $this->assertEquals('contacto@empresaabc.com', $client->email);
        $this->assertEquals('+507 1234-5678', $client->phone);
        $this->assertTrue($client->isActive);
    }

    public function test_client_can_have_null_email_and_phone()
    {
        $client = new Client(
            id: 2,
            name: 'Empresa Sin Contacto',
            email: null,
            phone: null
        );

        $this->assertNull($client->email);
        $this->assertNull($client->phone);
    }
}