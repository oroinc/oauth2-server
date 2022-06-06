<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadFrontendClientCredentialsClient extends AbstractFixture implements
    ContainerAwareInterface,
    DependentFixtureInterface
{
    public const OAUTH_CLIENT_ID        = 'AJTXmr8-u_KYkFdtYVabTCWrKwKeIorr';
    public const OAUTH_CLIENT_SECRET    = 'HtLCnAzXNZaK54nx734CYJYpJKz0ziw==';
    public const OAUTH_CLIENT_REFERENCE = 'OAuthFrontendClient';

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
        return [
            LoadOrganization::class,
            LoadCustomerUserData::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        /** @var CustomerUser $owner */
        $owner = $this->getReference(LoadCustomerUserData::EMAIL);

        $client = new Client();
        $client->setOrganization($this->getReference('organization'));
        $client->setName('test front application');
        $client->setGrants(['client_credentials']);
        $client->setOwnerEntity(get_class($owner), $owner->getId());
        $client->setIdentifier(self::OAUTH_CLIENT_ID);
        $client->setPlainSecret(self::OAUTH_CLIENT_SECRET);
        $client->setFrontend(true);

        $this->clientManager->updateClient($client);
        $this->setReference(self::OAUTH_CLIENT_REFERENCE, $client);
    }
}
