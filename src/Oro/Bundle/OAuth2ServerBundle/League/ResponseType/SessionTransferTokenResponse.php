<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\League\ResponseType;

use League\OAuth2\Server\ResponseTypes\AbstractResponseType;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\IssuedSessionTransferToken;
use Psr\Http\Message\ResponseInterface;

/**
 * Generates the token endpoint response for the session_transfer grant.
 *
 * Example response:
 *
 * {
 *     "token_type": "SessionTransfer",
 *     "access_token": "stt_...",
 *     "expires_in": 30
 * }
 */
final class SessionTransferTokenResponse extends AbstractResponseType
{
    private ?IssuedSessionTransferToken $sessionTransferToken = null;

    public function setSessionTransferToken(IssuedSessionTransferToken $sessionTransferToken): void
    {
        $this->sessionTransferToken = $sessionTransferToken;
    }

    #[\Override]
    public function generateHttpResponse(ResponseInterface $response): ResponseInterface
    {
        $sessionTransferToken = $this->getSessionTransferToken();

        try {
            $payload = json_encode(
                [
                    'token_type' => 'SessionTransfer',
                    'access_token' => $sessionTransferToken->getToken(),
                    'expires_in' => $sessionTransferToken->getExpiresIn(),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $exception) {
            throw new \LogicException('Unable to encode the Session Transfer Token response.', previous: $exception);
        }

        $response = $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');

        $response->getBody()->write($payload);

        return $response;
    }

    private function getSessionTransferToken(): IssuedSessionTransferToken
    {
        if (null === $this->sessionTransferToken) {
            throw new \LogicException('The Session Transfer Token must be set before generating the HTTP response.');
        }

        return $this->sessionTransferToken;
    }
}
