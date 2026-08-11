<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\League\Grant;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\ResponseType\SessionTransferTokenResponse;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\SessionTransferRouteValidator;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferSubjectResolver;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferTokenManager;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Exchanges an OAuth access token to a short-lived, one-time Session Transfer Token.
 *
 * Expected request:
 *
 * POST /oauth2-token
 *
 * {
 *     "grant_type": "session_transfer",
 *     "client_id": "mobile-storefront",
 *     "subject_token": "<current API access token>",
 *     "route": "oro_customer_frontend_customer_user_profile",
 *     "route_parameters": {
 *         "section": "orders"
 *     }
 * }
 */
final class SessionTransferGrant extends AbstractGrant
{
    public const string IDENTIFIER = Client::SESSION_TRANSFER;

    private const string SUBJECT_TOKEN_PARAMETER = 'subject_token';
    private const string ROUTE_PARAMETER = 'route';
    private const string ROUTE_PARAMETERS_PARAMETER = 'route_parameters';

    public function __construct(
        private readonly AuthorizationValidatorInterface $authorizationValidator,
        private readonly ClientManager $clientManager,
        private readonly ManagerRegistry $doctrine,
        private readonly SessionTransferSubjectResolver $subjectResolver,
        private readonly SessionTransferRouteValidator $routeValidator,
        private readonly SessionTransferTokenManager $tokenManager,
        private readonly SessionTransferTokenResponse $sessionTransferTokenResponse
    ) {
    }

    #[\Override]
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $leagueClient = $this->validateClient($request);
        $client = $this->clientManager->getClient((string) $leagueClient->getIdentifier());

        if (null === $client || !$client->isActive() || !$client->isSessionTransferAllowed()) {
            throw OAuthServerException::invalidClient($request);
        }

        $subjectToken = $this->getRequiredStringParameter(self::SUBJECT_TOKEN_PARAMETER, $request);
        $route = $this->getRequiredStringParameter(self::ROUTE_PARAMETER, $request);

        $routeParameters = $this->getRouteParameters($request);
        $validatedSubjectTokenRequest = $this->validateSubjectToken($request, $subjectToken);
        $sourceAccessTokenIdentifier = $this->getRequiredOAuthAttribute(
            $validatedSubjectTokenRequest,
            'oauth_access_token_id'
        );

        $sourceAccessToken = $this->getSourceAccessToken($sourceAccessTokenIdentifier);
        $this->validateSourceAccessToken($sourceAccessToken, $client);

        $userIdentifier = $this->subjectResolver->resolveUserIdentifier($sourceAccessToken);
        $target = $this->routeValidator->validate(
            $route,
            $routeParameters,
            $client
        );

        $issuedToken = $this->tokenManager->createToken(
            $client,
            $sourceAccessToken->getIdentifier(),
            $userIdentifier,
            $target,
            $accessTokenTTL
        );

        $response = clone $this->sessionTransferTokenResponse;
        $response->setSessionTransferToken($issuedToken);

        return $response;
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    private function validateSubjectToken(
        ServerRequestInterface $request,
        string $subjectToken
    ): ServerRequestInterface {
        $subjectTokenRequest = $request->withHeader('Authorization', 'Bearer ' . $subjectToken);

        try {
            return $this->authorizationValidator->validateAuthorization($subjectTokenRequest);
        } catch (OAuthServerException) {
            throw OAuthServerException::invalidGrant('The `subject_token` is invalid, expired or revoked.');
        }
    }

    private function getRequiredStringParameter(
        string $parameter,
        ServerRequestInterface $request
    ): string {
        $value = $this->getRequestParameter($parameter, $request);

        if (!\is_string($value) || '' === \trim($value)) {
            throw OAuthServerException::invalidRequest($parameter);
        }

        return \trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRouteParameters(
        ServerRequestInterface $request
    ): array {
        $routeParameters = $this->getRequestParameter(self::ROUTE_PARAMETERS_PARAMETER, $request, []);
        if (null === $routeParameters || '' === $routeParameters) {
            return [];
        }

        if (\is_string($routeParameters)) {
            try {
                $routeParameters = \json_decode($routeParameters, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw OAuthServerException::invalidRequest(
                    self::ROUTE_PARAMETERS_PARAMETER,
                    'The `route_parameters` parameter must contain a valid JSON object.',
                    $exception
                );
            }
        }

        if (!\is_array($routeParameters)) {
            throw OAuthServerException::invalidRequest(
                self::ROUTE_PARAMETERS_PARAMETER,
                'The `route_parameters` parameter must be an object.'
            );
        }

        foreach ($routeParameters as $name => $value) {
            if (!\is_string($name) || '' === \trim($name)) {
                throw OAuthServerException::invalidRequest(
                    self::ROUTE_PARAMETERS_PARAMETER,
                    'Every route parameter must have a non-empty string name.'
                );
            }

            $this->validateRouteParameterValue($value);
        }

        return $routeParameters;
    }

    private function validateRouteParameterValue(mixed $value): void
    {
        if (null === $value || \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value)) {
            return;
        }

        if (\is_array($value)) {
            foreach ($value as $nestedValue) {
                $this->validateRouteParameterValue($nestedValue);
            }

            return;
        }

        throw OAuthServerException::invalidRequest(
            self::ROUTE_PARAMETERS_PARAMETER,
            'Route parameter values may contain only scalar values, null or arrays.'
        );
    }

    private function getRequiredOAuthAttribute(ServerRequestInterface $request, string $attribute): string
    {
        $value = $request->getAttribute($attribute);

        if (!\is_string($value) && !\is_int($value)) {
            throw OAuthServerException::invalidGrant(
                \sprintf(
                    'The source access token does not contain the required `%s` attribute.',
                    $attribute
                )
            );
        }

        $value = (string) $value;

        if ('' === $value) {
            if ('oauth_user_id' === $attribute) {
                throw OAuthServerException::invalidGrant(
                    'The `subject_token` must represent a user or a customer visitor.'
                );
            }

            throw OAuthServerException::invalidGrant(
                \sprintf('The source access token contains an empty `%s` attribute.', $attribute)
            );
        }

        return $value;
    }

    private function getSourceAccessToken(
        string $identifier
    ): AccessToken {
        $manager = $this->doctrine->getManagerForClass(AccessToken::class);

        $accessToken = $manager
            ->getRepository(AccessToken::class)
            ->findOneBy(['identifier' => $identifier]);

        if (!$accessToken instanceof AccessToken) {
            throw OAuthServerException::invalidGrant('The source access token cannot be found.');
        }

        return $accessToken;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function validateSourceAccessToken(
        AccessToken $accessToken,
        Client $requestedClient
    ): void {
        if ($accessToken->isRevoked()) {
            throw OAuthServerException::invalidGrant('The source access token has been revoked.');
        }

        $expiresAt = $accessToken->getExpiresAt();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null === $expiresAt || $expiresAt <= $now) {
            throw OAuthServerException::invalidGrant('The source access token has expired.');
        }

        $sourceClient = $accessToken->getClient();
        if (
            null === $sourceClient
            || !\hash_equals((string) $requestedClient->getIdentifier(), (string) $sourceClient->getIdentifier())
        ) {
            throw OAuthServerException::invalidGrant(
                'The source access token was issued to another OAuth application.'
            );
        }

        if ($sourceClient->isFrontend() !== $requestedClient->isFrontend()) {
            throw OAuthServerException::invalidGrant(
                'The source access token application type does not match the Session Transfer application type.'
            );
        }

        if (
            null === $sourceClient->getOrganization()
            || null === $requestedClient->getOrganization()
            || $sourceClient->getOrganization()->getId() !== $requestedClient->getOrganization()->getId()
        ) {
            throw OAuthServerException::invalidGrant('The source access token belongs to another organization.');
        }
    }
}
