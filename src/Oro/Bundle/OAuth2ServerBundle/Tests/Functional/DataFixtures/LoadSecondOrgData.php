<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadSecondOrgData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    public const OAUTH_CLIENT_ID = 'IJ4RSZlEkkulaOUcqyF4PGNazD_KxLuq';
    public const OAUTH_CLIENT_SECRET = 'fU5ik3jhAEKxzXxoUYHhLCDY4kZwifjMokLypU5fPjBY4oZFysSpEj9';

    private const SECOND_ORGANIZATION = 'second_organization';
    private const SECOND_USER = 'second_user';

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
        return [LoadUser::class];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $this->createOrganization($manager);
        $this->createUser($manager);
        $this->createSecondOrgOauthApplication();
    }

    private function createOrganization(ObjectManager $manager): void
    {
        $organization = new Organization();
        $organization->setEnabled(true);
        $organization->setName('TestOrg2');
        $organization->addUser($this->getReference('user'));

        $manager->persist($organization);
        $manager->flush();

        $this->setReference(self::SECOND_ORGANIZATION, $organization);
    }

    private function createUser(ObjectManager $manager): void
    {
        $user = new User();
        $user->setFirstName('test');
        $user->setLastName('user');
        $user->setUsername(self::SECOND_USER);
        $user->setPassword('password');
        $user->setEmail('test.user@email.com');
        $user->setOrganization($this->getReference(self::SECOND_ORGANIZATION));
        $user->addOrganization($this->getReference(self::SECOND_ORGANIZATION));

        $manager->persist($user);
        $manager->flush();

        $this->setReference(self::SECOND_USER, $user);
    }

    private function createSecondOrgOauthApplication()
    {
        /** @var User $owner */
        $owner = $this->getReference('user');

        $client = new Client();
        $client->setOrganization($this->getReference(self::SECOND_ORGANIZATION));
        $client->setName('second org application');
        $client->setGrants(['client_credentials']);
        $client->setOwnerEntity(User::class, $owner->getId());
        $client->setIdentifier(self::OAUTH_CLIENT_ID);
        $client->setPlainSecret(self::OAUTH_CLIENT_SECRET);

        $this->clientManager->updateClient($client);
    }
}
