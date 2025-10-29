<?php

namespace Oro\Bundle\OAuth2ServerBundle\Api;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessor;

/**
 * Checks whether the current OAuth token grants access to API.
 */
class OAuth2TokenAccessChecker
{
    public function __construct(
        private readonly TokenAccessor $tokenAccessor,
        private readonly ApiDocViewProvider $apiDocViewProvider
    ) {
    }

    public function isAccessGranted(RequestType $requestType): bool
    {
        $token = $this->tokenAccessor->getToken();
        if (!$token instanceof OAuth2Token) {
            return true;
        }

        $client = $token->getAttribute('client');
        if (!$client instanceof Client) {
            return true;
        }

        if ($client->isAllApis()) {
            return true;
        }

        $views = $this->apiDocViewProvider->getViewsByRequestType($requestType);
        if ($views) {
            $allowedApis = $client->getApis();
            foreach ($views as $name => $view) {
                if (\in_array($name, $allowedApis, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
