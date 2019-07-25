<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * Provides a set of methods to simplify manage of the OAuth 2.0 Client entity.
 */
class ClientManager
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /**
     * @param ManagerRegistry               $doctrine
     * @param EncoderFactoryInterface       $encoderFactory
     * @param TokenAccessorInterface        $tokenAccessor
     * @param AuthorizationCheckerInterface $authorizationChecker
     */
    public function __construct(
        ManagerRegistry $doctrine,
        EncoderFactoryInterface $encoderFactory,
        TokenAccessorInterface $tokenAccessor,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->doctrine = $doctrine;
        $this->encoderFactory = $encoderFactory;
        $this->tokenAccessor = $tokenAccessor;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Checks whether access to view of Client entity is granted.
     *
     * @param Client|null $entity
     *
     * @return bool
     */
    public function isViewGranted(Client $entity = null): bool
    {
        return $this->authorizationChecker->isGranted('VIEW', $entity ?: 'entity:' . Client::class);
    }

    /**
     * Checks whether access to creation of Client entity is granted.
     *
     * @return bool
     */
    public function isCreationGranted(): bool
    {
        return $this->authorizationChecker->isGranted('CREATE', 'entity:' . Client::class);
    }

    /**
     * Checks whether access to modification of the given Client entity is granted.
     *
     * @param Client $entity
     *
     * @return bool
     */
    public function isModificationGranted(Client $entity): bool
    {
        return $this->authorizationChecker->isGranted('EDIT', $entity);
    }

    /**
     * Checks whether access to deletion of the given Client entity is granted.
     *
     * @param Client $entity
     *
     * @return bool
     */
    public function isDeletionGranted(Client $entity): bool
    {
        return $this->authorizationChecker->isGranted('DELETE', $entity);
    }

    /**
     * Sets missing, auto-generated and default values to the given Client entity
     * and store it to the database if the flushing is requested.
     *
     * @param Client $client The entity to update
     * @param bool   $flush  Whether to store the entity to the database (default true)
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function updateClient(Client $client, bool $flush = true): void
    {
        $this->setAutoGeneratedFields($client);
        if (!$client->getOrganization()) {
            $client->setOrganization($this->tokenAccessor->getOrganization());
        }
        if (!$client->getScopes() && null !== $client->getScopes()) {
            $client->setScopes(null);
        }
        if (!$client->getRedirectUris() && null !== $client->getRedirectUris()) {
            $client->setRedirectUris(null);
        }
        $ownerEntityClass = $client->getOwnerEntityClass();
        if ($ownerEntityClass && $ownerEntityClass !== User::class) {
            $client->setFrontend(true);
        }

        if ($flush) {
            $em = $this->getEntityManager();
            $em->persist($client);
            $em->flush();
        }
    }

    /**
     * Activates the given Client entity.
     *
     * @param Client $client The entity to be activated
     */
    public function activateClient(Client $client): void
    {
        $client->setActive(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Deactivates the given Client entity.
     *
     * @param Client $client The entity to be activated
     */
    public function deactivateClient(Client $client): void
    {
        $client->setActive(false);
        $this->getEntityManager()->flush();
    }

    /**
     * Deletes the given Client entity from the database.
     *
     * @param Client $client The entity to be activated
     */
    public function deleteClient(Client $client): void
    {
        $em = $this->getEntityManager();
        $em->remove($client);
        $em->flush();
    }

    /**
     * @param Client $client
     */
    private function setAutoGeneratedFields(Client $client): void
    {
        if (null === $client->getId()) {
            if (!$client->getIdentifier()) {
                $client->setIdentifier($this->generateToken(32));
            }
            if (!$client->getPlainSecret()) {
                $client->setPlainSecret($this->generateToken(128));
            }
        }
        if ($client->getPlainSecret()) {
            $salt = $this->generateToken(50);
            $client->setSecret(
                $this->getSecretEncoder($client)->encodePassword($client->getPlainSecret(), $salt),
                $salt
            );
        }
    }

    /**
     * @param Client $client
     *
     * @return PasswordEncoderInterface
     */
    private function getSecretEncoder(Client $client): PasswordEncoderInterface
    {
        return $this->encoderFactory->getEncoder($client);
    }

    /**
     * @param int $maxLength
     *
     * @return string
     */
    private function generateToken(int $maxLength): string
    {
        $randomString = '';
        $minLength = (int)($maxLength * 0.6);
        while (strlen($randomString) < $minLength) {
            $randomString .= rtrim(
                strtr(base64_encode(hash('sha256', random_bytes($maxLength), true)), '+/', '-_'),
                '='
            );
        }

        return str_shuffle(substr($randomString, 0, $maxLength));
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(Client::class);
    }
}
