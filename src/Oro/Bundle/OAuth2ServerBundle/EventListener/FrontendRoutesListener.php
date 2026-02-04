<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;

/**
 * Loads the storefront related routes and the back-office related routes for case when the storefront exists.
 */
class FrontendRoutesListener
{
    public function onCollectionAutoload(RouteCollectionEvent $event): void
    {
        $loader = new YamlFileLoader(new FileLocator(__DIR__ . '/../Resources/config/oro'));
        $event->getCollection()->addCollection($loader->load('frontend_routes.yml'));
    }
}
