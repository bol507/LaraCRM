<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    

    protected function tearDown(): void
    {
        \Mockery::close();  
        parent::tearDown();
    }
}