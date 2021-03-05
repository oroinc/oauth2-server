<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AuthCodeEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the authorization code entity repository for "league/oauth2-server" library.
 */
class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var UserLoaderInterface */
    private $userLoader;

    /** @var OAuthUserChecker */
    private $userChecker;

    /** @var ClientManager */
    private $clientManager;

    /**
     * @param ManagerRegistry     $doctrine
     * @param UserLoaderInterface $userLoader
     * @param OAuthUserChecker    $userChecker
     * @param ClientManager       $clientManager
     */
    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        ClientManager $clientManager
    ) {
        $this->doctrine = $doctrine;
        $this->userLoader = $userLoader;
        $this->userChecker = $userChecker;
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $em = $this->getEntityManager();
        $authCode = $this->findAuthCodeEntity($em, $authCodeEntity->getIdentifier());
        if (null !== $authCode) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }

        $authCode = new AuthCode(
            $authCodeEntity->getIdentifier(),
            \DateTime::createFromImmutable($authCodeEntity->getExpiryDateTime()),
            $this->getScopeIdentifiers($authCodeEntity->getScopes()),
            $this->getClientEntity($authCodeEntity->getClient()->getIdentifier()),
            $authCodeEntity->getUserIdentifier()
        );

        $em->persist($authCode);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAuthCode($codeId): void
    {
        $em = $this->getEntityManager();
        $authCode = $this->findAuthCodeEntity($em, $codeId);
        if (null === $authCode) {
            return;
        }

        $authCode->revoke();
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthCodeRevoked($codeId): bool
    {
        $authCode = $this->findAuthCodeEntity($this->getEntityManager(), $codeId);
        if (null === $authCode) {
            return true;
        }

        return $authCode->isRevoked();
    }

    /**
     * @param AuthCode $authCode
     *
     * @return UserLoaderInterface
     */
    protected function getUserLoader(AuthCode $authCode): UserLoaderInterface
    {
        return $this->userLoader;
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
     * @param EntityManagerInterface $em
     * @param string                 $codeId
     *
     * @return AuthCode|null
     */
    private function findAuthCodeEntity(EntityManagerInterface $em, string $codeId): ?AuthCode
    {
        $authCode = $em->getRepository(AuthCode::class)->findOneBy(['identifier' => $codeId]);
        if (null !== $authCode) {
            $userIdentifier = $authCode->getUserIdentifier();
            if (null === $userIdentifier) {
                throw OAuthServerException::invalidCredentials();
            }
            $this->checkUser($this->getUserLoader($authCode), $userIdentifier);
        }

        return $authCode;
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(AuthCode::class);
    }

    /**
     * @param string $clientId
     *
     * @return Client
     */
    private function getClientEntity(string $clientId): Client
    {
        return $this->clientManager->getClient($clientId);
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
