<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadAuthorizationCodeGrantClient extends AbstractFixture implements
    ContainerAwareInterface,
    DependentFixtureInterface
{
    public const OAUTH_CLIENT_ID        = 'OxvBGZ4Z0gG6Maihm2amg80LcSpJez4';
    public const OAUTH_CLIENT_SECRET    = 'fL4VT7mO0PC3l0m9woNTt7fAm6nxvajlMvd5n5s9JkFtEEaK0BwDua_-BY4KVxFqjmvE';
    public const OAUTH_CLIENT_REFERENCE = 'OAuthClient';

    public const PLAIN_CLIENT_REFERENCE = 'OAuthPlainClient';
    public const PLAIN_CLIENT_CLIENT_ID = 'CvqiP9CLcd5yn1hOGlUxBUGNkiPbzeb_';

    public const PLAIN_CLIENT_REFERENCE1 = 'OAuthPlainClient1';
    public const PLAIN_CLIENT_CLIENT_ID1 = 'CvqiP9CLcd5yn1hOGlUxBUGNkiPbzRb_';

    public const NON_PLAIN_CLIENT_REFERENCE = 'OAuthNonPlainClient';
    public const NON_PLAIN_CLIENT_CLIENT_ID = 'EWlEZCrAO74ZIHHokxoTYhYSms6R3MqG';

    /** @var ClientManager */
    private $clientManager;

    #[\Override]
    public function setContainer(?ContainerInterface $container = null)
    {
        $this->clientManager = $container->get('oro_oauth2_server.client_manager');
    }

    #[\Override]
    public function getDependencies()
    {
        return [LoadOrganization::class];
    }

    #[\Override]
    public function load(ObjectManager $manager)
    {
        $organization = $this->getReference(LoadOrganization::ORGANIZATION);

        $client = new Client();
        $client->setOrganization($organization);
        $client->setName('test Authorization Code Grant backend');
        $client->setGrants(['authorization_code']);
        $client->setIdentifier(self::OAUTH_CLIENT_ID);
        $client->setPlainSecret(self::OAUTH_CLIENT_SECRET);
        $client->setRedirectUris(['http://test.com']);
        $this->clientManager->updateClient($client);
        $this->setReference(self::OAUTH_CLIENT_REFERENCE, $client);

        $nonConfidentialPlainPCKEClient = new Client();
        $nonConfidentialPlainPCKEClient->setOrganization($organization);
        $nonConfidentialPlainPCKEClient->setName('test Non Confidential plain');
        $nonConfidentialPlainPCKEClient->setGrants(['authorization_code']);
        $nonConfidentialPlainPCKEClient->setIdentifier(self::PLAIN_CLIENT_CLIENT_ID);
        $nonConfidentialPlainPCKEClient->setRedirectUris(['http://test.com']);
        $nonConfidentialPlainPCKEClient->setConfidential(false);
        $nonConfidentialPlainPCKEClient->setPlainTextPkceAllowed(true);
        $this->clientManager->updateClient($nonConfidentialPlainPCKEClient);
        $this->setReference(self::PLAIN_CLIENT_REFERENCE, $nonConfidentialPlainPCKEClient);

        $nonConfidentialPlainPCKEClient1 = new Client();
        $nonConfidentialPlainPCKEClient1->setOrganization($organization);
        $nonConfidentialPlainPCKEClient1->setName('test Non Confidential plain1');
        $nonConfidentialPlainPCKEClient1->setGrants(['authorization_code']);
        $nonConfidentialPlainPCKEClient1->setIdentifier(self::PLAIN_CLIENT_CLIENT_ID1);
        $nonConfidentialPlainPCKEClient1->setRedirectUris(['http://test.com']);
        $nonConfidentialPlainPCKEClient1->setConfidential(false);
        $nonConfidentialPlainPCKEClient1->setPlainTextPkceAllowed(true);
        $this->clientManager->updateClient($nonConfidentialPlainPCKEClient1);
        $this->setReference(self::PLAIN_CLIENT_REFERENCE1, $nonConfidentialPlainPCKEClient1);

        $nonConfidentialNonPlainPCKEClient = new Client();
        $nonConfidentialNonPlainPCKEClient->setOrganization($organization);
        $nonConfidentialNonPlainPCKEClient->setName('test Non Confidential Non plain');
        $nonConfidentialNonPlainPCKEClient->setGrants(['authorization_code']);
        $nonConfidentialNonPlainPCKEClient->setIdentifier(self::NON_PLAIN_CLIENT_CLIENT_ID);
        $nonConfidentialNonPlainPCKEClient->setRedirectUris(['http://test.com']);
        $nonConfidentialNonPlainPCKEClient->setConfidential(false);
        $nonConfidentialNonPlainPCKEClient->setPlainTextPkceAllowed(false);
        $this->clientManager->updateClient($nonConfidentialNonPlainPCKEClient);
        $this->setReference(self::NON_PLAIN_CLIENT_REFERENCE, $nonConfidentialNonPlainPCKEClient);
    }
}
