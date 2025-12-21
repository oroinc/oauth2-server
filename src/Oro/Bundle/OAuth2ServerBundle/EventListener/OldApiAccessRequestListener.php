<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Api\OAuth2TokenAccessChecker;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns 403 response when access to old backoffice API is denied.
 */
class OldApiAccessRequestListener
{
    private readonly string $apiPattern;

    public function __construct(
        string $apiPattern,
        private readonly OAuth2TokenAccessChecker $accessChecker
    ) {
        $this->apiPattern = '{' . $apiPattern . '}';
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (preg_match($this->apiPattern, $event->getRequest()->getPathInfo()) !== 1) {
            return;
        }

        if (!$this->accessChecker->isAccessGranted(new RequestType([]))) {
            throw new AccessDeniedHttpException('This API is not accessible via the current OAuth2 token.');
        }
    }
}
