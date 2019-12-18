<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * The implementation of the client entity repository for "league/oauth2-server" library.
 */
class ClientRepository implements ClientRepositoryInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /** @var ApiFeatureChecker */
    private $featureChecker;

    /**
     * @param ManagerRegistry         $doctrine
     * @param EncoderFactoryInterface $encoderFactory
     * @param ApiFeatureChecker       $featureChecker
     */
    public function __construct(
        ManagerRegistry $doctrine,
        EncoderFactoryInterface $encoderFactory,
        ApiFeatureChecker $featureChecker
    ) {
        $this->doctrine = $doctrine;
        $this->encoderFactory = $encoderFactory;
        $this->featureChecker = $featureChecker;
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

        if ($mustValidateSecret && !$this->isPasswordValid($client, $clientSecret)) {
            return null;
        }

        if (!$client->getOrganization()->isEnabled()) {
            return null;
        }

        if (!$this->featureChecker->isEnabledByClient($client)) {
            throw new OAuthServerException('Not found', 404, 'not_available', 404);
        }

        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->getIdentifier());
        $clientEntity->setRedirectUri(\array_map('strval', $client->getRedirectUris()));
        $clientEntity->setFrontend($client->isFrontend());

        return $clientEntity;
    }

    /**
     * @param Client      $client
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

        // "refresh_token" grant should be supported for clients that support
        // "password" and "authorization_code" grants
        if ('refresh_token' === $grant
            && (
                \in_array('password', $grants, true)
                || \in_array('authorization_code', $grants, true)
            )
        ) {
            return true;
        }

        return \in_array($grant, $grants, true);
    }

    /**
     * @param Client      $client
     * @param string|null $clientSecret
     *
     * @return bool
     */
    private function isPasswordValid(Client $client, ?string $clientSecret): bool
    {
        return $this->encoderFactory
            ->getEncoder($client)
            ->isPasswordValid($client->getSecret(), (string)$clientSecret, $client->getSalt());
    }

    /**
     * @return ObjectRepository
     */
    private function getRepository(): ObjectRepository
    {
        return $this->doctrine->getRepository(Client::class);
    }
}
