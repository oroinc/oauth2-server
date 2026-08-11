<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Validates common Session Transfer target-route constraints.
 */
abstract class AbstractSessionTransferRouteContextValidator implements SessionTransferRouteContextValidatorInterface
{
    private const array FORBIDDEN_TARGET_ROUTES = [
        'oro_oauth2_session_transfer_consume',
        'oro_oauth2_frontend_session_transfer_consume',
    ];

    public function __construct(
        private readonly RouterInterface $router
    ) {
    }

    #[\Override]
    public function validate(string $route, array $routeParameters, Client $client): SessionTransferTarget
    {
        $this->validateRouteName($route);
        $this->validateRouteParameters($routeParameters);

        $routeDefinition = $this->router->getRouteCollection()->get($route);
        if (null === $routeDefinition) {
            throw OAuthServerException::invalidRequest(
                'route',
                \sprintf('The route "%s" does not exist.', $route)
            );
        }

        $this->validateRouteIsNotForbidden($route);
        $this->validateRouteContext($route, $routeDefinition);
        $this->validateRouteMethod($route, $routeDefinition);
        $this->validateRouteCanBeGenerated($route, $routeParameters);

        return new SessionTransferTarget(
            $route,
            $routeParameters,
            $this->getContextData($client)
        );
    }

    abstract protected function validateRouteContext(string $route, Route $routeDefinition): void;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getContextData(Client $client): array;

    private function validateRouteName(string $route): void
    {
        if ('' === $route) {
            throw OAuthServerException::invalidRequest('route', 'The route name must not be empty.');
        }
    }

    private function validateRouteIsNotForbidden(string $route): void
    {
        if (!\in_array($route, self::FORBIDDEN_TARGET_ROUTES, true)) {
            return;
        }

        throw OAuthServerException::invalidRequest(
            'route',
            \sprintf('The route "%s" cannot be used as a Session Transfer target.', $route)
        );
    }

    private function validateRouteParameters(array $routeParameters): void
    {
        foreach ($routeParameters as $name => $value) {
            if (!\is_string($name) || '' === $name) {
                throw OAuthServerException::invalidRequest(
                    'route_parameters',
                    'A route parameter name must be a non-empty string.'
                );
            }

            if (\str_starts_with($name, '_')) {
                throw OAuthServerException::invalidRequest(
                    'route_parameters',
                    \sprintf('The internal route parameter "%s" is not allowed.', $name)
                );
            }

            if (null !== $value && !\is_scalar($value)) {
                throw OAuthServerException::invalidRequest(
                    'route_parameters',
                    \sprintf('The route parameter "%s" must contain a scalar value or null.', $name)
                );
            }
        }
    }

    private function validateRouteMethod(string $route, Route $routeDefinition): void
    {
        $methods = $routeDefinition->getMethods();
        if ([] === $methods) {
            return;
        }

        if (\in_array(Request::METHOD_GET, $methods, true)) {
            return;
        }

        throw OAuthServerException::invalidRequest(
            'route',
            \sprintf('The route "%s" does not accept GET requests.', $route)
        );
    }

    private function validateRouteCanBeGenerated(string $route, array $routeParameters): void
    {
        try {
            $this->router->generate($route, $routeParameters);
        } catch (RoutingExceptionInterface | \InvalidArgumentException $exception) {
            throw OAuthServerException::invalidRequest(
                'route_parameters',
                \sprintf(
                    'The target URL cannot be generated for route "%s": %s',
                    $route,
                    $exception->getMessage()
                ),
                $exception
            );
        }
    }
}
