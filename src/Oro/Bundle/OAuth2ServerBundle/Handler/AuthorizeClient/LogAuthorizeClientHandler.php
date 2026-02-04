<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;

/**
 * The handler that logs the OAuth application authorization result.
 */
class LogAuthorizeClientHandler implements AuthorizeClientHandlerInterface
{
    public function __construct(
        private readonly UserLoginAttemptLogger $userLoginAttemptLogger
    ) {
    }

    #[\Override]
    public function handle(ClientEntity $clientEntity, UserInterface $user, bool $isAuthorized): void
    {
        if ($isAuthorized) {
            $this->userLoginAttemptLogger->logSuccessLoginAttempt(
                $user,
                'OAuthCode',
                ['OAuthApp' => ['identifier' => $clientEntity->getIdentifier()]]
            );
        } else {
            $this->userLoginAttemptLogger->logFailedLoginAttempt(
                $user,
                'OAuthCode',
                ['OAuthApp' => ['identifier' => $clientEntity->getIdentifier()]]
            );
        }
    }
}
