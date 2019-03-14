<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * This exception is thrown when an authentication is rejected because an encryption key file does not exist.
 */
class CryptKeyNotFoundException extends OAuthServerException
{
    /**
     * @param \Throwable $previous The previous exception
     *
     * @return CryptKeyNotFoundException
     */
    public static function create(\Throwable $previous = null): CryptKeyNotFoundException
    {
        return new static('The encryption key does not exist.', 0, 'no_encryption_key', 401, null, null, $previous);
    }
}
