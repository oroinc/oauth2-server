<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\OAuth2ServerBundle\ProtectedResource\ProtectedResource;
use Oro\Bundle\OAuth2ServerBundle\ProtectedResource\ProtectedResourceProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Returns information about OAuth protected resources.
 */
class ProtectedResourceController
{
    public function __construct(
        private readonly array $authorizationServerRoutes,
        private readonly ProtectedResourceProviderInterface $protectedResourceProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function resourceAction(string $resourcePath): Response
    {
        $normalizedResourcePath = '/' . $resourcePath;
        $resource = $this->findResource($normalizedResourcePath);
        if (null === $resource) {
            return new Response(
                'The resource not found.',
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'text/plain']
            );
        }

        $resourceData = [
            'resource_name' => $resource->getName(),
            'resource' => $this->generateAbsoluteUrl($resource->getRouteName(), $resource->getRouteParameters()),
            'authorization_servers' => [$this->generateAuthorizationServerUrl($normalizedResourcePath)],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => $resource->getSupportedScopes()
        ];
        $resourceOptions = $resource->getOptions();
        foreach ($resourceOptions as $name => $value) {
            $resourceData[$name] = $value;
        }

        return new JsonResponse($resourceData);
    }

    private function findResource(string $normalizedResourcePath): ?ProtectedResource
    {
        $resource = $this->protectedResourceProvider->getProtectedResource($normalizedResourcePath);
        if (null === $resource) {
            return null;
        }

        $resolvedResourcePath = $this->urlGenerator->generate(
            $resource->getRouteName(),
            $resource->getRouteParameters()
        );
        if ($resolvedResourcePath !== $normalizedResourcePath) {
            $this->logger->error(
                'Invalid configuration for the OAuth protected resource. The configured path "{configuredPath}"'
                . ' does not match the path "{resolvedPath}" that is resolved by the configured route.',
                ['configuredPath' => $normalizedResourcePath, 'resolvedPath' => $resolvedResourcePath]
            );

            return null;
        }

        return $resource;
    }

    private function generateAuthorizationServerUrl(string $normalizedResourcePath): string
    {
        $authorizationServerRouteName = null;
        foreach ($this->authorizationServerRoutes as $prefix => $routeName) {
            if (!$prefix || str_starts_with($normalizedResourcePath, $prefix)) {
                $authorizationServerRouteName = $routeName;
                break;
            }
        }

        return $this->generateAbsoluteUrl($authorizationServerRouteName);
    }

    private function generateAbsoluteUrl(string $routeName, array $routeParameters = []): string
    {
        return $this->urlGenerator->generate($routeName, $routeParameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
