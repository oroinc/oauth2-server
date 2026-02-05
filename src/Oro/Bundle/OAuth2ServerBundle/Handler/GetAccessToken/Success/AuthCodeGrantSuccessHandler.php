<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use League\OAuth2\Server\CryptTrait;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles successfully processed OAuth auth code grant access token.
 */
class AuthCodeGrantSuccessHandler implements SuccessHandlerInterface
{
    use CryptTrait;

    public function __construct(
        private AuthCodeLogAttemptHelper $logAttemptHelper,
        private RequestStack $requestStack,
        private InteractiveLoginEventDispatcher $interactiveLoginEventDispatcher,
    ) {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): void
    {
        if ('authorization_code' !== ((array)$request->getParsedBody())['grant_type']) {
            return;
        }

        $this->logAttemptHelper->logSuccessLoginAttempt($request, 'OAuth');

        $symfonyRequest = $this->requestStack->getMainRequest();
        if (null !== $symfonyRequest) {
            [$userId, $clientId] = $this->getUserIdAndClientId($request);
            if ($userId) {
                [$userIdentifier, $visitorSessionId] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($userId);
                if ($visitorSessionId) {
                    $symfonyRequest->attributes->set('visitor_session_id', $visitorSessionId);
                }
                $this->interactiveLoginEventDispatcher->dispatch($symfonyRequest, $clientId, $userIdentifier);
            }
        }
    }

    /**
     * @return array{?string,?string}
     */
    private function getUserIdAndClientId(ServerRequestInterface $request): array
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

        $userId = null;
        $clientId = null;
        if (\property_exists($authCodePayload, 'user_id')) {
            $userId = $authCodePayload->user_id;
        }
        if ($userId && \property_exists($authCodePayload, 'client_id')) {
            $clientId = $authCodePayload->client_id;
        }

        return [$userId, $clientId];
    }
}
