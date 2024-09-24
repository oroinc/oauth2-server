<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Grant;

use DateInterval;
use League\OAuth2\Server\Grant\AuthCodeGrant as LeagueAuthCodeGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The AuthCodeGrant that validates the client for confidential as well as for public clients.
 */
class AuthCodeGrant extends LeagueAuthCodeGrant
{
    #[\Override]
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        [$clientId] = $this->getClientCredentials($request);
        $client = $this->getClientEntityOrFail($clientId, $request);
        if (!$client->isConfidential()) {
            $this->validateClient($request);
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }
}
