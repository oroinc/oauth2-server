<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Changes layouts for storefront login routes.
 * Adds layout for storefront authenticate route.
 */
class OauthLoginLayoutListener
{
    private const AUTHENTICATE_ROUTE = 'oro_oauth2_server_frontend_authenticate';

    private array $routes = [];

    public function __construct(
        private ClientManager $clientManager,
        private LayoutListener $layoutListener
    ) {
    }

    public function addRoute(string $route): void
    {
        $this->routes[] = $route;
    }

    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (in_array($route, $this->routes, true)) {
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session) {
                parse_str(
                    parse_url($session->get('_security.frontend.target_path'), PHP_URL_QUERY),
                    $parameters
                );
                if (array_key_exists('client_id', $parameters) && is_array($event->getControllerResult())) {
                    $controllerResult = $event->getControllerResult();
                    $controllerResult['data']['appName'] = $this->clientManager
                        ->getClient($parameters['client_id'])
                        ->getName();
                    $controllerResult['route_name'] = 'oauth_' . $request->attributes->get('_route');
                    $event->setControllerResult($controllerResult);

                    $request->attributes->set('_oauth_login', true);
                }
            }
        }

        if ($route === self::AUTHENTICATE_ROUTE) {
            $layout = new Layout();
            $request->attributes->set('_layout', $layout);
        }

        $this->layoutListener->onKernelView($event);
    }
}
