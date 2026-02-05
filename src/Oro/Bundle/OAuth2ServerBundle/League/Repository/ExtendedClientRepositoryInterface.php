<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Extends the client repository interface to be able to search a client
 * using additional data from an incoming HTTP request.
 */
interface ExtendedClientRepositoryInterface extends ClientRepositoryInterface
{
    /**
     * Checks if the given client identifier represents a special identifier
     * that can be handled by this repository.
     */
    public function isSpecialClientIdentifier(string $clientIdentifier): bool;

    /**
     * Finds a client by its identifier and additional data from the incoming HTTP request.
     *
     * @throws OAuthServerException when the given client identifier represents a special identifier
     *                              that can be handled by this repository, but OAuth client cannot be found
     *                              due to the given request contains invalid parameters
     */
    public function findClientEntity(
        string $clientIdentifier,
        ServerRequestInterface $request
    ): ?ClientEntityInterface;
}
