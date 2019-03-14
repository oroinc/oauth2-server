<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repositories;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entities\ClientEntity;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * The implementation of client entity repository for "league/oauth2-server" library.
 */
class ClientRepository implements ClientRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /**
     * @param ManagerRegistry         $doctrine
     * @param EncoderFactoryInterface $encoderFactory
     */
    public function __construct(ManagerRegistry $doctrine, EncoderFactoryInterface $encoderFactory)
    {
        $this->doctrine = $doctrine;
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientEntity(
        $clientIdentifier,
        $grantType = null,
        $clientSecret = null,
        $mustValidateSecret = true
    ): ?ClientEntityInterface {
        /** @var Client $client */
        $client = $this->getRepository()->findOneBy(['identifier' => $clientIdentifier]);

        if (null === $client) {
            return null;
        }

        if (!$client->isActive()) {
            return null;
        }

        if (!$this->isGrantSupported($client, $grantType)) {
            return null;
        }

        $secretEncoder = $this->getSecretEncoder($client);
        if (!$secretEncoder->isPasswordValid($client->getSecret(), (string) $clientSecret, $client->getSalt())) {
            return null;
        }

        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->getIdentifier());
        $clientEntity->setRedirectUri(array_map('strval', $client->getRedirectUris()));

        return $clientEntity;
    }

    /**
     * @param Client $client
     * @param string|null $grant
     *
     * @return bool
     */
    private function isGrantSupported(Client $client, ?string $grant): bool
    {
        if (null === $grant) {
            return true;
        }

        $grants = $client->getGrants();

        if (empty($grants)) {
            return true;
        }

        return \in_array($grant, $client->getGrants(), true);
    }

    /**
     * @return ObjectRepository
     */
    private function getRepository(): ObjectRepository
    {
        return $this->doctrine->getRepository(Client::class);
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
}
