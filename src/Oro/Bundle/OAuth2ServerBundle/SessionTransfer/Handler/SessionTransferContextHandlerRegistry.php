<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Selects the context handler that can create a session for a Session Transfer Token.
 */
final class SessionTransferContextHandlerRegistry
{
    /**
     * @param iterable<SessionTransferContextHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers
    ) {
    }

    public function getHandler(SessionTransferToken $transferToken): SessionTransferContextHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if (!$handler->supports($transferToken)) {
                continue;
            }

            return $handler;
        }

        $client = $transferToken->getClient();
        throw new ServiceUnavailableHttpException(
            null,
            $client->isFrontend()
                ? 'Storefront Session Transfer is not available.'
                : 'Back-office Session Transfer is not available.'
        );
    }
}
