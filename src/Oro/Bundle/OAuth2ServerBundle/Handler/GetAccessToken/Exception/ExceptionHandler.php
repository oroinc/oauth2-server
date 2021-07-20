<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Delegates exception handling when getting of OAuth access token to child handlers.
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /** @var ExceptionHandlerInterface[] */
    private $handlers;

    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($request, $exception);
        }
    }
}
