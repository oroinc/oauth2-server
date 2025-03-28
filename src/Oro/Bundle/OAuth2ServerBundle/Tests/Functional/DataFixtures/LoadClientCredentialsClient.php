<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadClientCredentialsClient extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    public const OAUTH_CLIENT_ID        = 'OxvBGZ4Z0gG6Maihm2amg80LcSpJez3W';
    public const OAUTH_CLIENT_SECRET    = 'fL4VT7mO0PC3l0m9woNTt7fAm6nxvajlMvd5n5s9JkFtEEaK0BwDua_-BY4KVxFqjmvE';
    public const OAUTH_CLIENT_REFERENCE = 'OAuthClient';

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
        return [
            LoadOrganization::class,
            LoadUser::class
        ];
    }

    #[\Override]
    public function load(ObjectManager $manager)
    {
        /** @var User $owner */
        $owner = $this->getReference(LoadUser::USER);

        $client = new Client();
        $client->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
        $client->setName('test application');
        $client->setGrants(['client_credentials']);
        $client->setOwnerEntity(get_class($owner), $owner->getId());
        $client->setIdentifier(self::OAUTH_CLIENT_ID);
        $client->setPlainSecret(self::OAUTH_CLIENT_SECRET);

        $this->clientManager->updateClient($client);
        $this->setReference(self::OAUTH_CLIENT_REFERENCE, $client);
    }
}
