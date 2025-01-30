<?php

namespace Oro\Bundle\OAuth2ServerBundle\Client;

use Oro\Bundle\OAuth2ServerBundle\Controller\AuthorizationTokenController;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Server\AuthorizationServer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Makes an internal request to provide an OAuth2 access token.
 */
class AccessTokenClient
{
    public function __construct(
        private HttpKernelInterface $httpKernel,
        private AuthorizationServer $authorizationServer,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @param string $clientIdentifier OAuth2 client identifier.
     * @param string $authCode Authorization code.
     * @param string $codeVerifier Authorization code verifier.
     * @param \DateInterval|null $ttlDateInterval TTL for access token.
     *
     * @return array Example:
     *    [
     *        'token_type' => 'Bearer',
     *        'expires_in' => 3600,
     *        'access_token' => 'token',
     *        'refresh_token' => 'token'
     *    ]
     * @throws \JsonException
     */
    public function getTokenByAuthorizationCode(
        string $clientIdentifier,
        string $authCode,
        string $codeVerifier,
        ?\DateInterval $ttlDateInterval = null
    ): array {
        $requestBody = [
            'grant_type' => Client::AUTHORIZATION_CODE,
            'client_id' => $clientIdentifier,
            'code' => $authCode,
            'code_verifier' => $codeVerifier,
        ];

        $response = $this->sendRequest($requestBody, $ttlDateInterval);

        return json_decode((string)$response->getContent(), true, 2, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $clientIdentifier OAuth2 client identifier.
     * @param string $refreshToken
     * @param \DateInterval|null $ttlDateInterval TTL for access token.
     *
     * @return array Example:
     *    [
     *        'token_type' => 'Bearer',
     *        'expires_in' => 3600,
     *        'access_token' => 'token',
     *        'refresh_token' => 'token'
     *    ]
     * @throws \JsonException
     */
    public function getTokenByRefreshToken(
        string $clientIdentifier,
        string $refreshToken,
        ?\DateInterval $ttlDateInterval = null
    ): array {
        $requestBody = [
            'grant_type' => Client::REFRESH_TOKEN,
            'client_id' => $clientIdentifier,
            'refresh_token' => $refreshToken,
        ];

        $response = $this->sendRequest($requestBody, $ttlDateInterval);

        return json_decode((string)$response->getContent(), true, 2, JSON_THROW_ON_ERROR);
    }

    private function sendRequest(
        array $requestBody,
        ?\DateInterval $ttlDateInterval = null
    ): Response {
        if ($ttlDateInterval !== null) {
            if (empty($requestBody['grant_type'])) {
                throw new \LogicException('The parameter "grant_type" is expected to be not empty.');
            }

            $originalTtl = $this->authorizationServer->getGrantTypeAccessTokenTTL($requestBody['grant_type']);
            $this->authorizationServer->setGrantTypeAccessTokenTTL($requestBody['grant_type'], $ttlDateInterval);
        }

        try {
            $attributes = ['_controller' => AuthorizationTokenController::class . '::tokenAction'];

            $currentRequest = $this->requestStack->getCurrentRequest();
            if ($currentRequest) {
                $subRequest = $currentRequest->duplicate([], $requestBody, $attributes);
            } else {
                $subRequest = Request::create('/', Request::METHOD_POST, $requestBody);
                $subRequest->attributes->add($attributes);
            }

            return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        } finally {
            if (!empty($originalTtl)) {
                $this->authorizationServer->setGrantTypeAccessTokenTTL($requestBody['grant_type'], $originalTtl);
            }
        }
    }
}
