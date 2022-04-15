<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs the success getting of access token for auth code grant.
 */
class AuthCodeGrantSuccessHandler implements SuccessHandlerInterface
{
    private AuthCodeLogAttemptHelper $logAttemptHelper;

    public function __construct(AuthCodeLogAttemptHelper $logAttemptHelper)
    {
        $this->logAttemptHelper = $logAttemptHelper;
    }

    public function handle(ServerRequestInterface $request): void
    {
        if ('authorization_code' !== $request->getParsedBody()['grant_type']) {
            return;
        }

        $this->logAttemptHelper->logSuccessLoginAttempt($request, 'OAuth');
    }
}
