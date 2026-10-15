<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Component\DependencyInjection\ContainerAwareInterface;
use Oro\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Loads storefront OAuth applications with the "authorization_code" grant
 * that allow and do not allow the logout.
 */
class LoadFrontendLogoutClients extends AbstractFixture implements
    ContainerAwareInterface,
    DependentFixtureInterface
{
    use ContainerAwareTrait;

    public const string LOGOUT_ALLOWED_CLIENT_ID = 'logoutAllowedFrontendClient';
    public const string LOGOUT_NOT_ALLOWED_CLIENT_ID = 'logoutNotAllowedFrontendClient';
    public const string REDIRECT_URI = 'http://test.com';
    public const string POST_LOGOUT_REDIRECT_URI = 'http://test.com/logged-out';

    #[\Override]
    public function getDependencies(): array
    {
        return [LoadOrganization::class];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $this->createClient(self::LOGOUT_ALLOWED_CLIENT_ID, true);
        $this->createClient(self::LOGOUT_NOT_ALLOWED_CLIENT_ID, false);
    }

    private function createClient(string $identifier, bool $logoutAllowed): void
    {
        $client = new Client();
        $client->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
        $client->setName($identifier);
        $client->setGrants(['authorization_code']);
        $client->setIdentifier($identifier);
        $client->setPlainSecret($identifier . 'Secret');
        $client->setRedirectUris([self::REDIRECT_URI]);
        $client->setPostLogoutRedirectUris([self::POST_LOGOUT_REDIRECT_URI]);
        $client->setLogoutAllowed($logoutAllowed);
        $client->setFrontend(true);

        $this->container->get('oro_oauth2_server.client_manager')->updateClient($client);

        $this->setReference($identifier, $client);
    }
}
