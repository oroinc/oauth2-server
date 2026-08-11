<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Validates storefront target routes and captures the current website context.
 */
final class FrontendSessionTransferRouteContextValidator extends AbstractSessionTransferRouteContextValidator
{
    public function __construct(
        RouterInterface $router,
        private readonly WebsiteManager $websiteManager
    ) {
        parent::__construct($router);
    }

    #[\Override]
    public function supports(Client $client): bool
    {
        return $client->isFrontend();
    }

    #[\Override]
    protected function validateRouteContext(string $route, Route $routeDefinition): void
    {
        if (true === $routeDefinition->getOption('frontend')) {
            return;
        }

        throw OAuthServerException::invalidRequest(
            'route',
            \sprintf('The route "%s" is not a storefront route.', $route)
        );
    }

    #[\Override]
    protected function getContextData(Client $client): array
    {
        $website = $this->websiteManager->getCurrentWebsite();
        if (null === $website) {
            throw OAuthServerException::invalidGrant('The current website cannot be found.');
        }

        $websiteId = $website->getId();
        if (null === $websiteId) {
            throw OAuthServerException::invalidGrant('The current website does not have an identifier.');
        }

        $websiteOrganization = $website->getOrganization();
        if (null === $websiteOrganization) {
            throw OAuthServerException::invalidGrant('The current website is not assigned to an organization.');
        }

        $clientOrganization = $client->getOrganization();
        if (null === $clientOrganization) {
            throw OAuthServerException::invalidGrant('The OAuth application is not assigned to an organization.');
        }

        if ($websiteOrganization->getId() !== $clientOrganization->getId()) {
            throw OAuthServerException::invalidGrant(
                'The current website does not belong to the OAuth application organization.'
            );
        }

        return ['website_id' => $websiteId];
    }
}
