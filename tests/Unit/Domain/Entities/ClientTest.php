<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function test_client_can_be_created_with_valid_data()
    {
        $client = new Client(
            accountid: 1,
            account_no: 'ACC-001',
            accountname: 'Empresa ABC',
            email1: 'contacto@empresaabc.com',
            phone: '+507 1234-5678'
        );

        $this->assertEquals(1, $client->accountid);
        $this->assertEquals('Empresa ABC', $client->accountname);
        $this->assertEquals('contacto@empresaabc.com', $client->email1);
        $this->assertEquals('+507 1234-5678', $client->phone);
        $this->assertTrue($client->isActive);
    }

    public function test_client_can_have_null_email_and_phone()
    {
        $client = new Client(
            accountid: 2,
            account_no: 'ACC-002',
            accountname: 'Empresa Sin Contacto',
            email1: null,
            phone: null
        );

        $this->assertNull($client->email1);
        $this->assertNull($client->phone);
    }
}
