<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;

/**
 * The implementation of the scope entity repository for "league/oauth2-server" library.
 */
class ScopeRepository implements ScopeRepositoryInterface
{
    #[\Override]
    public function getScopeEntityByIdentifier($identifier)
    {
        $scopeEntity = new ScopeEntity();
        $scopeEntity->setIdentifier($identifier);

        return $scopeEntity;
    }

    #[\Override]
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        return $scopes;
    }
}
