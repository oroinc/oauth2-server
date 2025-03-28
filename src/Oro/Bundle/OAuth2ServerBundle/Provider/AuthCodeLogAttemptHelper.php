<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use League\OAuth2\Server\CryptTrait;
use Oro\Bundle\CustomerBundle\Security\CustomerUserLoader;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\UserBundle\Security\UserLoader;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The helper class that simplify the logging of the AuthCode ServerRequest.
 */
class AuthCodeLogAttemptHelper
{
    use CryptTrait;

    private ClientManager $clientManager;
    private UserLoader $backendUserLoader;
    private UserLoginAttemptLogger $backendLogger;
    private ?CustomerUserLoader $frontendUserLoader;
    private ?UserLoginAttemptLogger $frontendLogger;

    public function __construct(
        ClientManager $clientManager,
        UserLoader $backendUserLoader,
        UserLoginAttemptLogger $backendLogger,
        ?CustomerUserLoader $frontendUserLoader,
        ?UserLoginAttemptLogger $frontendLogger
    ) {
        $this->clientManager = $clientManager;
        $this->backendUserLoader = $backendUserLoader;
        $this->backendLogger = $backendLogger;
        $this->frontendUserLoader = $frontendUserLoader;
        $this->frontendLogger = $frontendLogger;
    }

    public function logFailedLoginAttempt(ServerRequestInterface $request, string $source, \Exception $exception): void
    {
        $this->logAttempt($request, $source, false, $exception);
    }

    public function logSuccessLoginAttempt(ServerRequestInterface $request, string $source): void
    {
        $this->logAttempt($request, $source, true);
    }

    private function logAttempt(
        ServerRequestInterface $request,
        string $source,
        bool $isSuccess,
        ?\Exception $exception = null
    ): void {
        $parameters = $request->getParsedBody();
        $isFrontendRequest = false;
        $user = null;
        try {
            $authCodePayload = json_decode($this->decrypt($parameters['code']), null, 512, JSON_THROW_ON_ERROR);
            [$userIdentifier] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($authCodePayload->user_id);
            $client = $this->clientManager->getClient($parameters['client_id']);
            if ($this->frontendUserLoader !== null && $client?->isFrontend()) {
                $isFrontendRequest = true;
                if (!VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
                    $user = $this->frontendUserLoader->loadUser($userIdentifier);
                }
            } else {
                $user = $this->backendUserLoader->loadUser($userIdentifier);
            }
        } catch (\Exception $e) {
        }
        if (null === $user) {
            return;
        }

        $logger = $isFrontendRequest ? $this->frontendLogger : $this->backendLogger;
        if (null !== $logger) {
            if ($isSuccess) {
                $logger->logSuccessLoginAttempt($user, $source);
            } else {
                $logger->logFailedLoginAttempt($user, $source, ['exception' => $exception]);
            }
        }
    }
}
