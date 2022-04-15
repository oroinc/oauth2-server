<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs the success getting of access token for client credentials grant.
 */
class ClientCredentialsGrantSuccessHandler implements SuccessHandlerInterface
{
    private ManagerRegistry $doctrine;
    private ClientManager $clientManager;
    private UserLoginAttemptLogger $backendLogger;
    private ?UserLoginAttemptLogger $frontendLogger = null;

    public function __construct(
        ManagerRegistry $doctrine,
        ClientManager $clientManager,
        UserLoginAttemptLogger $backendLogger,
        ?UserLoginAttemptLogger $frontendLogger
    ) {
        $this->doctrine = $doctrine;
        $this->clientManager = $clientManager;
        $this->backendLogger = $backendLogger;
        $this->frontendLogger = $frontendLogger;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): void
    {
        $parameters = $request->getParsedBody();
        if ('client_credentials' !== $parameters['grant_type']) {
            return;
        }

        $client = $this->clientManager->getClient($parameters['client_id']);
        $user = $this->doctrine->getRepository($client->getOwnerEntityClass())->find($client->getOwnerEntityId());
        if (null !== $this->frontendLogger && $client->isFrontend()) {
            $this->frontendLogger->logSuccessLoginAttempt($user, 'OAuth');
        } else {
            $this->backendLogger->logSuccessLoginAttempt($user, 'OAuth');
        }
    }
}
