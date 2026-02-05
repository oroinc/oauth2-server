<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Exception;

use Psr\Log\LogLevel;

/**
 * This exception is thrown when an authentication is rejected because an encryption key file does not exist.
 */
class CryptKeyNotFoundException extends ExtendedOAuthServerException
{
    public static function create(?\Throwable $previous = null): self
    {
        $e = new static('The encryption key does not exist.', 0, 'no_encryption_key', 401, null, null, $previous);
        $e->withLogLevel(LogLevel::WARNING);

        return $e;
    }
}
