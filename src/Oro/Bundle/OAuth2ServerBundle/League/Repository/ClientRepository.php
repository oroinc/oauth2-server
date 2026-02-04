<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Factory\ClientEntityFactoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * The implementation of the client entity repository for "league/oauth2-server" library.
 */
class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private readonly ClientManager $clientManager,
        private readonly ClientEntityFactoryInterface $clientEntityFactory,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly ApiFeatureChecker $featureChecker,
        private readonly OAuthUserChecker $userChecker,
        private readonly ManagerRegistry $doctrine
    ) {
    }

    #[\Override]
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        /** @var Client $client */
        $client = $this->getClient($clientIdentifier);
        if (null === $client) {
            return null;
        }

        if (!$this->isClientEnabled($client)) {
            return null;
        }

        return $this->clientEntityFactory->createClientEntity($client);
    }

    #[\Override]
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        $client = $this->getClient($clientIdentifier);
        if (null === $client) {
            return false;
        }

        if (!$this->isClientEnabled($client)) {
            return false;
        }

        if (!$this->isClientOwnerActive($client)) {
            return false;
        }

        if (!$this->isGrantSupported($client, $grantType)) {
            return false;
        }

        if ($client->isConfidential() && !$this->passwordHasherVerify($client, $clientSecret)) {
            return false;
        }

        return true;
    }

    private function getClient(string $clientId): ?Client
    {
        return $this->clientManager->getClient($clientId);
    }

    private function isClientEnabled(Client $client): bool
    {
        return
            $client->isActive()
            && $this->featureChecker->isEnabledByClient($client)
            && $client->getOrganization()->isEnabled();
    }

    private function isClientOwnerActive(Client $client): bool
    {
        $ownerClass = $client->getOwnerEntityClass();
        $ownerId = $client->getOwnerEntityId();
        if (null === $ownerClass || null === $ownerId || !is_a($ownerClass, UserInterface::class, true)) {
            return true;
        }

        $owner = $this->doctrine->getRepository($ownerClass)->find($ownerId);
        if (null === $owner) {
            return false;
        }

        try {
            $this->userChecker->checkUser($owner);
        } catch (OAuthServerException $e) {
            return false;
        }

        return true;
    }

    private function isGrantSupported(Client $client, ?string $grant): bool
    {
        if (null === $grant) {
            return true;
        }

        $grants = $client->getGrants();
        if (empty($grants)) {
            return true;
        }

        // "refresh_token" grant should be supported for clients that support
        // "password" and "authorization_code" grants
        if (
            'refresh_token' === $grant
            && (
                \in_array('password', $grants, true)
                || \in_array('authorization_code', $grants, true)
            )
        ) {
            return true;
        }

        return \in_array($grant, $grants, true);
    }

    private function passwordHasherVerify(Client $client, ?string $clientSecret): bool
    {
        return $this->passwordHasherFactory
            ->getPasswordHasher($client::class)
            ->verify($client->getSecret(), (string)$clientSecret, $client->getSalt());
    }
}
