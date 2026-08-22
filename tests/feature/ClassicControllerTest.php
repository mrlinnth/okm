<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class ClassicControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testIndexReturnsOk(): void
    {
        $result = $this->get('/classic');

        $result->assertStatus(200);
    }

    public function testListKeysStubReturnsJson(): void
    {
        $result = $this->post('/classic/keys/list');

        $result->assertStatus(200);
        $result->assertJSON();
    }
}
