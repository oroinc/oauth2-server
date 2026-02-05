<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use League\OAuth2\Server\CryptTrait;
use Oro\Bundle\CustomerBundle\Security\CustomerUserLoader;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
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
        $user = null;
        $logger = $this->backendLogger;
        try {
            [$userIdentifier, $client] = $this->getUserIdentifierAndClient($request);
            if ($userIdentifier) {
                if (null !== $this->frontendUserLoader && $client?->isFrontend()) {
                    $logger = $this->frontendLogger;
                    if (!VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
                        $user = $this->frontendUserLoader->loadUser($userIdentifier);
                    }
                } else {
                    $user = $this->backendUserLoader->loadUser($userIdentifier);
                }
            }
        } catch (\Exception) {
            return;
        }

        if (null !== $user && null !== $logger) {
            if ($isSuccess) {
                $logger->logSuccessLoginAttempt($user, $source);
            } else {
                $logger->logFailedLoginAttempt($user, $source, ['exception' => $exception]);
            }
        }
    }

    /**
     * @return array{?string,?Client}
     */
    private function getUserIdentifierAndClient(ServerRequestInterface $request): array
    {
        $encryptedAuthCode = ((array)$request->getParsedBody())['code'] ?? null;
        if (!\is_string($encryptedAuthCode)) {
            return [null, null];
        }

        try {
            $authCodePayload = \json_decode($this->decrypt($encryptedAuthCode), flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [null, null];
        }

        if (!\is_object($authCodePayload)) {
            return [null, null];
        }

        $userIdentifier = null;
        $client = null;
        if (\property_exists($authCodePayload, 'user_id')) {
            [$userIdentifier] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($authCodePayload->user_id);
        }
        if ($userIdentifier && \property_exists($authCodePayload, 'client_id')) {
            $client = $this->clientManager->getClient($authCodePayload->client_id);
        }

        return [$userIdentifier, $client];
    }
}
