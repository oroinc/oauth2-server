<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a way to extract a visitor session identifier from a visitor access token.
 */
class VisitorAccessTokenParser
{
    public function __construct(
        private AuthorizationValidatorInterface $authorizationValidator,
        private ServerRequestFactoryInterface $serverRequestFactory,
        private LoggerInterface $logger
    ) {
    }

    public function getVisitorSessionId(string $visitorAccessToken): ?string
    {
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', '/')
            ->withHeader('authorization', 'Bearer ' . $visitorAccessToken);
        try {
            $validatedRequest = $this->authorizationValidator->validateAuthorization($request);
        } catch (\Exception $e) {
            $this->logger->warning(
                'The visitor access token is invalid.',
                ['visitor_access_token' => $visitorAccessToken, 'exception' => $e]
            );

            return null;
        }

        $userId = $validatedRequest->getAttribute('oauth_user_id');
        if (!$userId) {
            $this->logger->warning(
                'The visitor access token does not contains a visitor identifier.',
                ['visitor_access_token' => $visitorAccessToken]
            );

            return null;
        }
        if (!VisitorIdentifierUtil::isVisitorIdentifier($userId)) {
            $this->logger->warning(
                'The visitor access token does not contains a valid visitor identifier.',
                ['visitor_access_token' => $visitorAccessToken]
            );

            return null;
        }

        return VisitorIdentifierUtil::decodeIdentifier($userId);
    }
}
