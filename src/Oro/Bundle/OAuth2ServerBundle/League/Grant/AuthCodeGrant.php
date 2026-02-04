<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\AuthCodeGrant as LeagueAuthCodeGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ExtendedClientRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The auth code grant.
 * Adds validation for both confidential and public clients.
 * Adds support of auth codes that contains information about a visitor that requests an access token for a user.
 * Adds support of indirect client identifiers, e.g. a client identifier can be a URL
 * for OAuth Client ID Metadata Document (CIMD) {@link https://oauth.net/2/client-id-metadata-document/}.
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
    protected function getClientEntityOrFail($clientId, ServerRequestInterface $request)
    {
        if ($this->clientRepository instanceof ExtendedClientRepositoryInterface) {
            $client = $this->clientRepository->findClientEntity($clientId, $request);
            if ($client instanceof ClientEntityInterface) {
                return $client;
            }
        }

        return parent::getClientEntityOrFail($clientId, $request);
    }

    #[\Override]
    protected function getClientCredentials(ServerRequestInterface $request)
    {
        [$clientId, $clientSecret] = parent::getClientCredentials($request);
        if (
            $this->clientRepository instanceof ExtendedClientRepositoryInterface
            && $this->clientRepository->isSpecialClientIdentifier($clientId)
        ) {
            $clientIdExtractedFromAuthCode = $this->tryToGetClientIdFromAuthCode($request);
            if ($clientIdExtractedFromAuthCode) {
                $clientId = $clientIdExtractedFromAuthCode;
            }
        }

        return [$clientId, $clientSecret];
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

    private function tryToGetClientIdFromAuthCode(ServerRequestInterface $request): ?string
    {
        $encryptedAuthCode = $this->getRequestParameter('code', $request);
        if (!\is_string($encryptedAuthCode)) {
            return null;
        }

        try {
            $authCodePayload = \json_decode($this->decrypt($encryptedAuthCode), flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return \is_object($authCodePayload) && \property_exists($authCodePayload, 'client_id')
            ? $authCodePayload->client_id
            : null;
    }
}
