<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;

/**
 * Loads the back-office related routes for case when the storefront does not exist.
 */
class NoFrontendRoutesListener
{
    public function onCollectionAutoload(RouteCollectionEvent $event): void
    {
        $loader = new YamlFileLoader(new FileLocator(__DIR__ . '/../Resources/config/oro'));
        $event->getCollection()->addCollection($loader->load('no_frontend_routes.yml'));
    }
}
