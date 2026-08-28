<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class SubscriptionsControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testIndexReturnsOk(): void
    {
        $this->get('/subscriptions')->assertStatus(200);
    }
}
