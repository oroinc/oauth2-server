<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use League\OAuth2\Server\CryptTrait;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Oro\Bundle\OAuth2ServerBundle\Provider\ExtractClientIdTrait;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles successfully processed OAuth auth code grant access token.
 */
class AuthCodeGrantSuccessHandler implements SuccessHandlerInterface
{
    use ExtractClientIdTrait;
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
        $parameters = $request->getParsedBody();
        if ('authorization_code' !== $parameters['grant_type']) {
            return;
        }

        $this->logAttemptHelper->logSuccessLoginAttempt($request, 'OAuth');

        $symfonyRequest = $this->requestStack->getMainRequest();
        if (null !== $symfonyRequest) {
            $authCodePayload = json_decode($this->decrypt($parameters['code']), null, 512, JSON_THROW_ON_ERROR);
            [$userIdentifier, $visitorSessionId] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier(
                $authCodePayload->user_id
            );
            if ($visitorSessionId) {
                $symfonyRequest->attributes->set('visitor_session_id', $visitorSessionId);
            }
            $this->interactiveLoginEventDispatcher->dispatch(
                $symfonyRequest,
                $this->getClientId($request),
                $userIdentifier
            );
        }
    }
}
