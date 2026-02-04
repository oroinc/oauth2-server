<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ExtendedClientRepositoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Changes layouts for storefront login routes.
 * Adds layout for storefront authenticate route.
 */
class OAuthLoginLayoutListener
{
    private const AUTHENTICATE_ROUTE = 'oro_oauth2_server_frontend_authenticate';

    private array $routes = [];

    public function __construct(
        private readonly LayoutListener $layoutListener,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ServerRequestFactoryInterface $serverRequestFactory
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
        if (\in_array($route, $this->routes, true)) {
            $targetRequest = $this->getTargetRequest($request);
            if (null !== $targetRequest) {
                $clientId = $this->getClientId($targetRequest);
                if ($clientId) {
                    $controllerResult = $event->getControllerResult();
                    if (\is_array($controllerResult)) {
                        $request->attributes->set('_oauth_login', true);
                        $controllerResult['data']['appName'] = $this->getClientName($clientId, $targetRequest);
                        $controllerResult['route_name'] = 'oauth_' . $route;
                        $event->setControllerResult($controllerResult);
                    }
                }
            }
        }

        if (self::AUTHENTICATE_ROUTE === $route) {
            $request->attributes->set('_layout', new Layout());
        }

        $this->layoutListener->onKernelView($event);
    }

    private function getTargetRequest(Request $request): ?ServerRequestInterface
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if (null === $session) {
            return null;
        }
        $targetUri = $session->get('_security.frontend.target_path');
        if (!$targetUri) {
            return null;
        }

        parse_str(parse_url($targetUri, PHP_URL_QUERY), $parameters);

        return $this->serverRequestFactory->createServerRequest('GET', $targetUri)
            ->withQueryParams($parameters);
    }

    private function getClientId(ServerRequestInterface $targetRequest): ?string
    {
        return $targetRequest->getQueryParams()['client_id'] ?? null;
    }

    private function getClientName(string $clientId, ServerRequestInterface $targetRequest): ?string
    {
        return $this->getClient($clientId, $targetRequest)?->getName();
    }

    private function getClient(string $clientId, ServerRequestInterface $targetRequest): ?ClientEntityInterface
    {
        if (
            $this->clientRepository instanceof ExtendedClientRepositoryInterface
            && $this->clientRepository->isSpecialClientIdentifier($clientId)
        ) {
            try {
                return $this->clientRepository->findClientEntity($clientId, $targetRequest);
            } catch (OAuthServerException) {
                return null;
            }
        }

        return $this->clientRepository->getClientEntity($clientId);
    }
}
