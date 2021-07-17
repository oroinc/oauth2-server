<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Delegates handling of successfully processed getting of OAuth access token to child handlers.
 */
class SuccessHandler implements SuccessHandlerInterface
{
    /** @var SuccessHandlerInterface[] */
    private $handlers;

    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($request);
        }
    }
}
