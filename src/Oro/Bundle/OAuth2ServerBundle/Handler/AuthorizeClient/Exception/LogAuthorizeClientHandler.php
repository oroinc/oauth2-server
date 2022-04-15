<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs the exception during the OAuth application authorization failure.
 */
class LogAuthorizeClientHandler
{
    private AuthCodeLogAttemptHelper $logAttemptHelper;

    public function __construct(AuthCodeLogAttemptHelper $logAttemptHelper)
    {
        $this->logAttemptHelper = $logAttemptHelper;
    }

    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        $parameters = $request->getParsedBody();
        if (!isset($parameters['grant_type']) || 'authorization_code' !== $parameters['grant_type']) {
            return;
        }

        $this->logAttemptHelper->logFailedLoginAttempt($request, 'OAuthCode', $exception);
    }
}
