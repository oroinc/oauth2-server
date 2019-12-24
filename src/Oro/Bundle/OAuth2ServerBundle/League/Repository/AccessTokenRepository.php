<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;

/**
 * The implementation of the access token entity repository for "league/oauth2-server" library.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ): AccessTokenEntityInterface {
        return new AccessTokenEntity();
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

        $client = $this->getClientEntity($accessTokenEntity->getClient()->getIdentifier());
        $client->setLastUsedAt(new \DateTime('now', new \DateTimeZone('UTC')));
        $accessToken = new AccessToken(
            $accessTokenEntity->getIdentifier(),
            $accessTokenEntity->getExpiryDateTime(),
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

    /**
     * @return EntityManagerInterface
     */
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
     * @param string $clientId
     *
     * @return Client
     */
    private function getClientEntity($clientId): Client
    {
        return $this->getEntityManager()
            ->getRepository(Client::class)
            ->findOneBy(['identifier' => $clientId]);
    }

    /**
     * @param ScopeEntity[] $scopes
     *
     * @return string[]
     */
    private function getScopeIdentifiers(array $scopes): array
    {
        return array_map(
            function (ScopeEntity $scope) {
                return $scope->getIdentifier();
            },
            $scopes
        );
    }
}
