<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Oro\Bundle\OAuth2ServerBundle\EventListener\FrontendRoutesListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class FrontendRoutesListenerTest extends TestCase
{
    public function testOnCollectionAutoload(): void
    {
        $listener = new FrontendRoutesListener();

        $collection = new RouteCollection();
        $collection->add('existing', new Route('some_path'));
        $event = new RouteCollectionEvent($collection);

        $listener->onCollectionAutoload($event);

        $routes = $collection->all();
        self::assertSame(
            [
                'existing',
                'oro_oauth2_server_auth_token',
                'oro_oauth2_server_auth_token_options',
                'oro_oauth2_server_protected_resource',
                'oro_oauth2_server_metadata_well_known_alias',
                'oro_oauth2_server_metadata',
                'oro_oauth2_server_authenticate',
                'oro_oauth2_server_frontend_metadata',
                'oro_oauth2_server_frontend_authenticate',
                'oro_oauth2_server_frontend_authenticate_visitor'
            ],
            array_keys($routes)
        );
    }
}
