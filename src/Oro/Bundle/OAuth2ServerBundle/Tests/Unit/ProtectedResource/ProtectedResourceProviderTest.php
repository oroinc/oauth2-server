<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\ProtectedResource;

use Oro\Bundle\OAuth2ServerBundle\ProtectedResource\ProtectedResourceProvider;
use PHPUnit\Framework\TestCase;

class ProtectedResourceProviderTest extends TestCase
{
    private ProtectedResourceProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->provider = new ProtectedResourceProvider([
            '/test/resource' => [
                'name' => 'Test Resource',
                'route' => 'test_route',
                'route_params' => ['param1' => 'value'],
                'supported_scopes' => ['read'],
                'options' => ['option1' => 'value1']
            ]
        ]);
    }

    public function testGetProtectedResource(): void
    {
        $resource = $this->provider->getProtectedResource('/test/resource');

        self::assertEquals('Test Resource', $resource->getName());
        self::assertEquals('test_route', $resource->getRouteName());
        self::assertEquals(['param1' => 'value'], $resource->getRouteParameters());
        self::assertEquals(['read'], $resource->getSupportedScopes());
        self::assertEquals(['option1' => 'value1'], $resource->getOptions());
    }

    public function testGetProtectedResourceForNonExistentResource(): void
    {
        self::assertNull($this->provider->getProtectedResource('/test/nonexistent'));
    }
}
