<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ExtendedClientRepositoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Changes layouts for storefront login routes.
 * Adds layout for storefront authenticate route.
 */
class OauthLoginLayoutListener
{
    private const AUTHENTICATE_ROUTE = 'oro_oauth2_server_frontend_authenticate';

    private ClientRepositoryInterface $clientRepository;
    private ServerRequestFactoryInterface $serverRequestFactory;
    private array $routes = [];

    public function __construct(
        private ClientManager $clientManager,
        private LayoutListener $layoutListener
    ) {
    }

    public function setClientRepository(ClientRepositoryInterface $clientRepository): void
    {
        $this->clientRepository = $clientRepository;
    }

    public function setServerRequestFactory(ServerRequestFactoryInterface $serverRequestFactory): void
    {
        $this->serverRequestFactory = $serverRequestFactory;
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
        if ($this->clientRepository instanceof ExtendedClientRepositoryInterface
            && $this->clientRepository->isSpecialClientIdentifier($clientId)
        ) {
            return $this->clientRepository->findClientName($clientId, $targetRequest);
        }

        return $this->clientRepository->getClientEntity($clientId)?->getName();
    }
}
