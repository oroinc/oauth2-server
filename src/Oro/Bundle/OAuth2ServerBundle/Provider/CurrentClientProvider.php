<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Provides the OAuth2 client that authenticated the current security token:
 * the client of the OAuth2 access token of an API request,
 * or the client that opened the current session via Session Transfer.
 */
class CurrentClientProvider
{
    /**
     * The security token attribute that holds the OAuth2 client entity of an API request.
     */
    public const string CLIENT_ATTRIBUTE = 'client';

    /**
     * The security token attribute that holds the database id of the OAuth2 client
     * that opened the current session via Session Transfer.
     */
    public const string CLIENT_ID_ATTRIBUTE = 'client_id';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ManagerRegistry $doctrine
    ) {
    }

    /**
     * Gets the OAuth2 client that authenticated the current security token,
     * or null when the token was not authenticated by an OAuth2 client.
     */
    public function getClient(): ?Client
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        if ($token->hasAttribute(self::CLIENT_ATTRIBUTE)) {
            $client = $token->getAttribute(self::CLIENT_ATTRIBUTE);
            if ($client instanceof Client) {
                return $client;
            }
        }

        if ($token->hasAttribute(self::CLIENT_ID_ATTRIBUTE)) {
            $clientId = $token->getAttribute(self::CLIENT_ID_ATTRIBUTE);
            if (\is_int($clientId)) {
                return $this->doctrine->getManagerForClass(Client::class)->find(Client::class, $clientId);
            }
        }

        return null;
    }
}
