<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadBusinessUnit;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadCleanupCommandData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    /** @var UserManager */
    private $userManager;

    /** @var ClientManager */
    private $clientManager;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->userManager = $container->get('oro_user.manager');
        $this->clientManager = $container->get('oro_oauth2_server.client_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadOrganization::class,
            LoadBusinessUnit::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $user1 = $this->createUser('1');
        $manager->persist($user1);
        $manager->flush();
        $this->setReference('user1', $user1);

        $client1 = $this->createClient('client1', User::class, $user1->getId());
        $manager->persist($client1);
        $client2 = $this->createClient('client2', User::class, $user1->getId() + 9999);
        $manager->persist($client2);

        $notExpiredAccessToken = $this->createAccessToken(
            $client1,
            'client1_token_not_expired',
            new \DateTime('now + 1 minute')
        );
        $manager->persist($notExpiredAccessToken);
        $expiredAccessToken = $this->createAccessToken(
            $client1,
            'client1_token_expired',
            new \DateTime('now - 1 second')
        );
        $manager->persist($expiredAccessToken);
        $expiredAccessTokenWithNotExpiredRefreshToken = $this->createAccessToken(
            $client1,
            'client1_token_expired_refresh_token_not_expired',
            new \DateTime('now - 1 second')
        );
        $manager->persist($expiredAccessTokenWithNotExpiredRefreshToken);
        $accessTokenForClientThatShouldBeRemoved = $this->createAccessToken(
            $client2,
            'client2_token_not_expired',
            new \DateTime('now + 1 minute')
        );
        $manager->persist($accessTokenForClientThatShouldBeRemoved);

        $manager->persist(
            $this->createRefreshToken(
                'expired_refresh_token',
                new \DateTime('now - 1 second'),
                $notExpiredAccessToken
            )
        );
        $manager->persist(
            $this->createRefreshToken(
                'not_expired_refresh_token',
                new \DateTime('now + 1 minute'),
                $notExpiredAccessToken
            )
        );
        $manager->persist(
            $this->createRefreshToken(
                'expired_refresh_token_for_expired_access_token',
                new \DateTime('now - 1 second'),
                $expiredAccessTokenWithNotExpiredRefreshToken
            )
        );
        $manager->persist(
            $this->createRefreshToken(
                'not_expired_refresh_token_for_expired_access_token',
                new \DateTime('now + 1 minute'),
                $expiredAccessTokenWithNotExpiredRefreshToken
            )
        );
        $manager->persist(
            $this->createRefreshToken(
                'refresh_token_for_client_that_should_be_removed',
                new \DateTime('now + 1 minute'),
                $accessTokenForClientThatShouldBeRemoved
            )
        );
        $manager->persist(
            $this->createAuthCode(
                $client1,
                'auth_code_not_expired',
                new \DateTime('now + 1 minute')
            )
        );
        $manager->persist(
            $this->createAuthCode(
                $client1,
                'auth_code_expired',
                new \DateTime('now - 1 second')
            )
        );

        $manager->flush();
    }

    private function createUser(string $key): User
    {
        $user = new User();
        $user->setOrganization($this->getReference('organization'));
        $user->setOwner($this->getReference('business_unit'));
        $user->setFirstName('fn' . $key);
        $user->setLastName('ln' . $key);
        $user->setUsername('user' . $key);
        $user->setEmail('user' . $key . '@example.com');
        $user->setPassword($this->userManager->generatePassword());
        $this->userManager->updateUser($user, false);

        return $user;
    }

    private function createClient(string $identifier, string $ownerEntityClass, int $ownerEntityId): Client
    {
        $client = new Client();
        $client->setOrganization($this->getReference('organization'));
        $client->setName($identifier);
        $client->setIdentifier($identifier);
        $client->setGrants(['client_credentials']);
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);

        $this->clientManager->updateClient($client, false);

        return $client;
    }

    private function createAccessToken(Client $client, string $identifier, \DateTime $expiresAt): AccessToken
    {
        return new AccessToken($identifier, $expiresAt, ['all'], $client);
    }

    private function createRefreshToken(
        string $identifier,
        \DateTime $expiresAt,
        AccessToken $accessToken
    ): RefreshToken {
        return new RefreshToken($identifier, $expiresAt, $accessToken);
    }

    private function createAuthCode(Client $client, string $identifier, \DateTime $expiresAt): AuthCode
    {
        return new AuthCode($identifier, $expiresAt, ['all'], $client, 'test');
    }
}
