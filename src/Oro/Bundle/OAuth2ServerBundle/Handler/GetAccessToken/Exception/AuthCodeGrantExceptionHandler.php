<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs failed login attempts for authorization_code grant.
 */
class AuthCodeGrantExceptionHandler implements ExceptionHandlerInterface
{
    private AuthCodeLogAttemptHelper $logAttemptHelper;

    public function __construct(AuthCodeLogAttemptHelper $logAttemptHelper)
    {
        $this->logAttemptHelper = $logAttemptHelper;
    }

    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        if ('authorization_code' !== $request->getParsedBody()['grant_type']) {
            return;
        }

        $this->logAttemptHelper->logFailedLoginAttempt($request, 'OAuth', $exception);
    }
}
