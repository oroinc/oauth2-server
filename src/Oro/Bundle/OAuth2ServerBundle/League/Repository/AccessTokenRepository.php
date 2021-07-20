<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;

/**
 * The implementation of the access token entity repository for "league/oauth2-server" library.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var ClientManager */
    private $clientManager;

    public function __construct(ManagerRegistry $doctrine, ClientManager $clientManager)
    {
        $this->doctrine = $doctrine;
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ): AccessTokenEntityInterface {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        $accessToken->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        return $accessToken;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $accessToken = $this->findAccessTokenEntity($accessTokenEntity->getIdentifier());
        if (null !== $accessToken) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }

        $client = $this->clientManager->getClient($accessTokenEntity->getClient()->getIdentifier());
        $client->setLastUsedAt(new \DateTime('now', new \DateTimeZone('UTC')));
        $accessToken = new AccessToken(
            $accessTokenEntity->getIdentifier(),
            \DateTime::createFromImmutable($accessTokenEntity->getExpiryDateTime()),
            $this->getScopeIdentifiers($accessTokenEntity->getScopes()),
            $client,
            $accessTokenEntity->getUserIdentifier()
        );

        $em = $this->getEntityManager();
        $em->persist($accessToken);
        $em->persist($client);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAccessToken($tokenId): void
    {
        $accessToken = $this->findAccessTokenEntity($tokenId);
        if (null === $accessToken) {
            return;
        }

        $accessToken->revoke();

        $em = $this->getEntityManager();
        $em->persist($accessToken);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function isAccessTokenRevoked($tokenId): bool
    {
        $accessToken = $this->findAccessTokenEntity($tokenId);
        if (null === $accessToken) {
            return true;
        }

        return $accessToken->isRevoked();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(AccessToken::class);
    }

    /**
     * @param string $tokenId
     *
     * @return AccessToken|null
     */
    private function findAccessTokenEntity($tokenId): ?AccessToken
    {
        return $this->getEntityManager()->getRepository(AccessToken::class)
            ->findOneBy(['identifier' => $tokenId]);
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     *
     * @return string[]
     */
    private function getScopeIdentifiers(array $scopes): array
    {
        return array_map(
            function (ScopeEntityInterface $scope) {
                return $scope->getIdentifier();
            },
            $scopes
        );
    }
}
