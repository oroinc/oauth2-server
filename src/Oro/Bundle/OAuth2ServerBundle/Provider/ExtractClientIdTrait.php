<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Provides a method to extract the "client_id" from a request.
 */
trait ExtractClientIdTrait
{
    private function getClientId(ServerRequestInterface $request): string
    {
        $basicAuthUser = null;
        if ($request->hasHeader('Authorization')) {
            $header = $request->getHeader('Authorization')[0];
            if (str_starts_with($header, 'Basic ')) {
                $decoded = \base64_decode(\substr($header, 6));
                if ($decoded && str_contains($decoded, ':')) {
                    [$basicAuthUser] = explode(':', $decoded, 2);
                }
            }
        }
        $requestParameters = (array)$request->getParsedBody();
        $clientId = $requestParameters['client_id'] ?? $basicAuthUser;
        if (null === $clientId) {
            throw OAuthServerException::invalidRequest('client_id');
        }

        return $clientId;
    }
}
