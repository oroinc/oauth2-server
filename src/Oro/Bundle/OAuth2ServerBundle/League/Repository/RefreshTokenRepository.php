<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\RefreshTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the refresh token entity repository for "league/oauth2-server" library.
 */
class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var UserLoaderInterface */
    private $userLoader;

    /** @var OAuthUserChecker */
    private $userChecker;

    /**
     * @param ManagerRegistry     $doctrine
     * @param UserLoaderInterface $userLoader
     * @param OAuthUserChecker    $userChecker
     */
    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker
    ) {
        $this->doctrine = $doctrine;
        $this->userLoader = $userLoader;
        $this->userChecker = $userChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $em = $this->getEntityManager();
        $refreshToken = $this->findRefreshTokenEntity($em, $refreshTokenEntity->getIdentifier());
        if (null !== $refreshToken) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }

        $accessToken = $em->getRepository(AccessToken::class)
            ->findOneBy(['identifier' => $refreshTokenEntity->getAccessToken()->getIdentifier()]);

        $refreshToken = new RefreshToken(
            $refreshTokenEntity->getIdentifier(),
            $refreshTokenEntity->getExpiryDateTime(),
            $accessToken
        );

        $em->persist($refreshToken);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function revokeRefreshToken($tokenId): void
    {
        $em = $this->getEntityManager();
        $refreshToken = $this->findRefreshTokenEntity($em, $tokenId);
        if (null === $refreshToken) {
            return;
        }

        $refreshToken->revoke();

        $em->persist($refreshToken);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function isRefreshTokenRevoked($tokenId): bool
    {
        $refreshToken = $this->findRefreshTokenEntity($this->getEntityManager(), $tokenId);

        return
            null === $refreshToken
            || $refreshToken->isRevoked();
    }

    /**
     * @param RefreshToken $token
     *
     * @return UserLoaderInterface
     */
    protected function getUserLoader(RefreshToken $token): UserLoaderInterface
    {
        return $this->userLoader;
    }

    /**
     * @param EntityManagerInterface $em
     * @param string                 $tokenId
     *
     * @return RefreshToken|null
     */
    private function findRefreshTokenEntity(EntityManagerInterface $em, string $tokenId): ?RefreshToken
    {
        $token = $em->getRepository(RefreshToken::class)->findOneBy(['identifier' => $tokenId]);

        if (null !== $token) {
            $userIdentifier = $token->getAccessToken()->getUserIdentifier();
            if (null === $userIdentifier) {
                throw OAuthServerException::invalidCredentials();
            }
            $this->checkUser($this->getUserLoader($token), $userIdentifier);
        }

        return $token;
    }

    /**
     * @param UserLoaderInterface $userLoader
     * @param string              $userIdentifier
     */
    protected function checkUser(UserLoaderInterface $userLoader, string $userIdentifier): void
    {
        $this->userChecker->checkUser($userLoader->loadUser($userIdentifier));
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(RefreshToken::class);
    }
}
