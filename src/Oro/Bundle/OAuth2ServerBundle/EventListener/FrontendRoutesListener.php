<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;

/**
 * Loads the storefront related routes.
 */
class FrontendRoutesListener
{
    public function onCollectionAutoload(RouteCollectionEvent $event): void
    {
        $loader = new YamlFileLoader(new FileLocator(__DIR__ . '/../Resources/config'));
        $frontendRoutes = $loader->load('frontend_oauth_routes.yml');
        $event->getCollection()->addCollection($frontendRoutes);
    }
}
