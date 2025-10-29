<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Server;

use League\OAuth2\Server\AuthorizationServer as LeagueAuthorizationServer;

/**
 * Overrides {@see LeagueAuthorizationServer} to add the ability to specify custom TTL for an access token.
 */
class AuthorizationServer extends LeagueAuthorizationServer
{
    public function setGrantTypeAccessTokenTTL(string $grantType, \DateInterval $dateInterval): void
    {
        $this->grantTypeAccessTokenTTL[$grantType] = $dateInterval;
    }

    public function getGrantTypeAccessTokenTTL(string $grantType): \DateInterval|null
    {
        return $this->grantTypeAccessTokenTTL[$grantType] ?? null;
    }
}
