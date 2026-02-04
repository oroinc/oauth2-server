<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Extends OAuthServerException to be able to add an additional information for logging.
 */
class ExtendedOAuthServerException extends OAuthServerException
{
    private ?string $logLevel = null;
    private array $logContext = [];

    public function getLogLevel(): ?string
    {
        return $this->logLevel;
    }

    /**
     * @param string $logLevel {@see \Psr\Log\LogLevel}
     */
    public function withLogLevel(string $logLevel): self
    {
        $this->logLevel = $logLevel;

        return $this;
    }

    public function getLogContext(): array
    {
        return $this->logContext;
    }

    public function withLogContext(array $logContext): self
    {
        $this->logContext = $logContext;

        return $this;
    }
}
