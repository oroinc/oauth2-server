<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ExtendedClientRepositoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Changes Twig templates for back-office login routes.
 */
class OAuthLoginTemplateListener
{
    private array $routes = [];

    public function __construct(
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
        if (!\in_array($request->attributes->get('_route'), $this->routes, true)) {
            return;
        }

        $targetRequest = $this->getTargetRequest($request);
        if (null === $targetRequest) {
            return;
        }

        $templateReference = $this->getTemplateReference($request);
        if (null === $templateReference) {
            return;
        }

        $clientId = $this->getClientId($targetRequest);
        if (!$clientId) {
            return;
        }

        $template = $templateReference->template;
        $templateReference->template = substr_replace($template, '@OroOAuth2Server', 0, strpos($template, '/'));
        $request->attributes->set('_oauth_login', true);

        $event->setControllerResult(array_merge(
            $event->getControllerResult(),
            ['appName' => $this->getClientName($clientId, $targetRequest)]
        ));
    }

    private function getTemplateReference(Request $request): ?Template
    {
        $template = $request->attributes->get('_template');
        if (\is_string($template)) {
            $template = new Template($template);
            $request->attributes->set('_template', $template);
        } elseif (!$template instanceof Template) {
            $template = null;
        }

        return $template;
    }

    private function getTargetRequest(Request $request): ?ServerRequestInterface
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if (null === $session) {
            return null;
        }
        $targetUri = $session->get('_security.main.target_path');
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
