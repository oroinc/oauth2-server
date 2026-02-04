<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\ProtectedResource;

use Oro\Bundle\OAuth2ServerBundle\ProtectedResource\ProtectedResource;
use PHPUnit\Framework\TestCase;

class ProtectedResourceTest extends TestCase
{
    public function testGetters(): void
    {
        $name = 'Test Resource';
        $routeName = 'test_route';
        $routeParameters = ['param1' => 'value'];
        $supportedScopes = ['read'];
        $options = ['option1' => 'value1'];

        $entity = new ProtectedResource(
            $name,
            $routeName,
            $routeParameters,
            $supportedScopes,
            $options
        );

        self::assertEquals($name, $entity->getName());
        self::assertEquals($routeName, $entity->getRouteName());
        self::assertEquals($routeParameters, $entity->getRouteParameters());
        self::assertEquals($supportedScopes, $entity->getSupportedScopes());
        self::assertEquals($options, $entity->getOptions());
    }
}
