<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadFrontendAuthorizationCodeGrantClient extends AbstractFixture implements
    ContainerAwareInterface,
    DependentFixtureInterface
{
    public const OAUTH_CLIENT_ID        = 'FxvBGZ4Z0gG6Maihm2amg80LcSpJezF';
    public const OAUTH_CLIENT_SECRET    = 'fL4VT7mO0PC3l0m9woNTt7fAm6nxvajlMvd5n5s9JkFtEEaK0BwDua_-BY4KVxFqjmvE';
    public const OAUTH_CLIENT_REFERENCE = 'FrontendOAuthClient';

    /** @var ClientManager */
    private $clientManager;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->clientManager = $container->get('oro_oauth2_server.client_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [LoadOrganization::class];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $client = new Client();
        $client->setOrganization($this->getReference('organization'));
        $client->setName('test Authorization Code Grant backend');
        $client->setGrants(['authorization_code']);
        $client->setIdentifier(self::OAUTH_CLIENT_ID);
        $client->setPlainSecret(self::OAUTH_CLIENT_SECRET);
        $client->setRedirectUris(['http://test.com']);
        $client->setFrontend(true);

        $this->clientManager->updateClient($client);

        $this->setReference(self::OAUTH_CLIENT_REFERENCE, $client);
    }
}
