<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;

/**
 * The handler that logs the OAuth application authorization result.
 */
class LogAuthorizeClientHandler implements AuthorizeClientHandlerInterface
{
    private UserLoginAttemptLogger $userLoginAttemptLogger;

    public function __construct(UserLoginAttemptLogger $userLoginAttemptLogger)
    {
        $this->userLoginAttemptLogger = $userLoginAttemptLogger;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Client $client, UserInterface $user, bool $isAuthorized): void
    {
        if ($isAuthorized) {
            $this->userLoginAttemptLogger->logSuccessLoginAttempt(
                $user,
                'OAuthCode',
                ['OAuthApp' => [
                    'id'         => $client->getId(),
                    'identifier' => $client->getIdentifier()
                ]]
            );
        } else {
            $this->userLoginAttemptLogger->logFailedLoginAttempt(
                $user,
                'OAuthCode',
                ['OAuthApp' => [
                    'id'         => $client->getId(),
                    'identifier' => $client->getIdentifier()
                ]]
            );
        }
    }
}
