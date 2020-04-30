<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents a handler for exception occurred when getting of OAuth access token.
 */
interface ExceptionHandlerInterface
{
    /**
     * Handles exceptions occurred when getting of OAuth access token.
     *
     * @param ServerRequestInterface $request
     * @param OAuthServerException   $exception
     */
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void;
}
