<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Provider\ExtractClientIdTrait;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs the success getting of access token for client credentials grant.
 */
class ClientCredentialsGrantSuccessHandler implements SuccessHandlerInterface
{
    use ExtractClientIdTrait;

    private ManagerRegistry $doctrine;
    private ClientManager $clientManager;
    private UserLoginAttemptLogger $backendLogger;
    private ?UserLoginAttemptLogger $frontendLogger;

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

    #[\Override]
    public function handle(ServerRequestInterface $request): void
    {
        $parameters = $request->getParsedBody();
        if ('client_credentials' !== $parameters['grant_type']) {
            return;
        }

        /** @var Client $client */
        $client = $this->clientManager->getClient($this->getClientId($request));
        $user = $this->doctrine->getRepository($client->getOwnerEntityClass())->find($client->getOwnerEntityId());
        if (null !== $this->frontendLogger && $client->isFrontend()) {
            $this->frontendLogger->logSuccessLoginAttempt($user, 'OAuth');
        } else {
            $this->backendLogger->logSuccessLoginAttempt($user, 'OAuth');
        }
    }
}
