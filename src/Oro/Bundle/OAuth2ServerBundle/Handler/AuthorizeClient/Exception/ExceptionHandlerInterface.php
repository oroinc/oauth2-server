<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents a handler for OAuth application authorization failure.
 */
interface ExceptionHandlerInterface
{
    /**
     * Handles exceptions occurred during OAuth application authorization.
     */
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void;
}
