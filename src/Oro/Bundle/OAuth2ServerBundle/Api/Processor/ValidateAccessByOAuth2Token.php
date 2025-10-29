<?php

namespace Oro\Bundle\OAuth2ServerBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Processor\Context;
use Oro\Bundle\OAuth2ServerBundle\Api\OAuth2TokenAccessChecker;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Denies access to API if the current OAuth token denies access to it.
 */
class ValidateAccessByOAuth2Token implements ProcessorInterface
{
    public function __construct(
        private readonly OAuth2TokenAccessChecker $accessChecker
    ) {
    }

    #[\Override]
    public function process(ContextInterface $context): void
    {
        /** @var Context $context */

        if (!$this->accessChecker->isAccessGranted($context->getRequestType())) {
            throw new AuthenticationException('This API is not accessible via the current OAuth2 token.');
        }
    }
}
