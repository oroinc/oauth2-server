<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Oro\Bundle\OAuth2ServerBundle\Provider\ExtractClientIdTrait;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles successfully processed OAuth password grant access token.
 */
class PasswordGrantSuccessHandler implements SuccessHandlerInterface
{
    use ExtractClientIdTrait;

    public function __construct(
        private RequestStack $requestStack,
        private InteractiveLoginEventDispatcher $interactiveLoginEventDispatcher,
        private VisitorAccessTokenParser $visitorAccessTokenParser
    ) {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): void
    {
        $parameters = $request->getParsedBody();
        if ('password' !== $parameters['grant_type']) {
            return;
        }

        $symfonyRequest = $this->requestStack->getMainRequest();
        if (null !== $symfonyRequest) {
            $visitorAccessToken = $parameters['visitor_access_token'] ?? null;
            if ($visitorAccessToken) {
                $visitorSessionId = $this->visitorAccessTokenParser->getVisitorSessionId($visitorAccessToken);
                if ($visitorSessionId) {
                    $symfonyRequest->attributes->set('visitor_session_id', $visitorSessionId);
                }
            }
            $this->interactiveLoginEventDispatcher->dispatch(
                $symfonyRequest,
                $this->getClientId($request),
                $parameters['username']
            );
        }
    }
}
