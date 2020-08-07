<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Oro\Bundle\OAuth2ServerBundle\EventListener\FrontendRoutesListener;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class FrontendRoutesListenerTest extends \PHPUnit\Framework\TestCase
{
    public function testOnCollectionAutoload()
    {
        $listener = new FrontendRoutesListener();

        $collection = new RouteCollection();
        $collection->add('existing', new Route('some_path'));
        $event = new RouteCollectionEvent($collection);

        $listener->onCollectionAutoload($event);

        $routes = $collection->all();
        self::assertCount(4, $routes);
        self::assertArrayHasKey('oro_oauth2_server_frontend_login_check', $routes);
        self::assertArrayHasKey('oro_oauth2_server_frontend_login_form', $routes);
        self::assertArrayHasKey('oro_oauth2_server_frontend_authenticate', $routes);
    }
}
