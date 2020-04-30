<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents a handler that is executed in case of getting of OAuth access token successfully processed.
 */
interface SuccessHandlerInterface
{
    /**
     * Handles successfully processed getting of OAuth access token.
     *
     * @param ServerRequestInterface $request
     */
    public function handle(ServerRequestInterface $request): void;
}
