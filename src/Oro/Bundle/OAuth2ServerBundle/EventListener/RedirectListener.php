<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\PlatformBundle\EventListener\RedirectListenerInterface;
use Oro\Bundle\RedirectBundle\Routing\Router;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Do not do redirect when the request is from OAuth
 */
class RedirectListener implements RedirectListenerInterface
{
    public function __construct(
        private readonly RedirectListenerInterface $innerRedirectListener,
        private readonly Router $router
    ) {
    }

    #[\Override]
    public function onRequest(RequestEvent $event): void
    {
        if ($this->isOAuthRequest($event)) {
            return;
        }

        $this->innerRedirectListener->onRequest($event);
    }

    private function isOAuthRequest(RequestEvent $event): bool
    {
        if ($event->isMainRequest()) {
            $pathInfo = $event->getRequest()->getPathInfo();

            try {
                $match = $this->router->matchRequest($event->getRequest());
            } catch (ResourceNotFoundException|MethodNotAllowedException $e) {
                $match = [];
            }

            if (isset($match['_route']) && str_starts_with($match['_route'], 'oro_oauth2_server')) {
                return true;
            }

            if (str_contains($pathInfo, '/oauth2-token')) {
                return true;
            }
        }

        return false;
    }
}
