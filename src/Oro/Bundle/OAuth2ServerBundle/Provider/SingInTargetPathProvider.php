<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\CustomerBundle\Layout\DataProvider\SignInTargetPathProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sign in target path provider decorator that returns null for OAuth 2 login pages
 * because in this case the session target path should be used.
 */
class SingInTargetPathProvider implements SignInTargetPathProviderInterface
{
    public function __construct(
        private SignInTargetPathProviderInterface $innerProvider,
        private RequestStack $requestStack
    ) {
    }

    public function getTargetPath(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->attributes->get('_oauth_login', false)) {
            return null;
        }

        return $this->innerProvider->getTargetPath();
    }
}
