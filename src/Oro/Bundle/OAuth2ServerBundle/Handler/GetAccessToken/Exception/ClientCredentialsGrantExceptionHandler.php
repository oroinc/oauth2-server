<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Provider\ExtractClientIdTrait;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logs the fail during getting of access token for client credentials grant.
 */
class ClientCredentialsGrantExceptionHandler implements ExceptionHandlerInterface
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
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        $parameters = $request->getParsedBody();
        if ('client_credentials' !== $parameters['grant_type']) {
            return;
        }

        $client = null;
        $user = null;
        try {
            $client = $this->clientManager->getClient($this->getClientId($request));
            if ($client) {
                $repo = $this->doctrine->getRepository($client->getOwnerEntityClass());
                $user = $repo->find($client->getOwnerEntityId());
            }
        } catch (\Throwable $e) {
        }

        if (null !== $this->frontendLogger && $client && $client->isFrontend()) {
            $this->frontendLogger->logFailedLoginAttempt($user, 'OAuth', ['exception' => $exception]);
        } else {
            $this->backendLogger->logFailedLoginAttempt($user, 'OAuth', ['exception' => $exception]);
        }
    }
}
