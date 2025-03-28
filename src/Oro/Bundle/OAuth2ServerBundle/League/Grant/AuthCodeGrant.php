<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\AuthCodeGrant as LeagueAuthCodeGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The auth code grant.
 * Adds validation for both confidential and public clients.
 * Adds support of auth codes that contains information about a visitor that requests an access token for a user.
 */
class AuthCodeGrant extends LeagueAuthCodeGrant
{
    #[\Override]
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ) {
        [$clientId] = $this->getClientCredentials($request);
        $client = $this->getClientEntityOrFail($clientId, $request);
        if (!$client->isConfidential()) {
            $this->validateClient($request);
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }

    #[\Override]
    protected function issueAccessToken(
        \DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        $userIdentifier,
        array $scopes = []
    ) {
        [$userIdentifier] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($userIdentifier);

        return parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
    }
}
