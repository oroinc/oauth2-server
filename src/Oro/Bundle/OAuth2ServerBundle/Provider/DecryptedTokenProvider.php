<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use League\OAuth2\Server\CryptTrait;

/**
 * Provides the decrypted version of the specified token.
 */
class DecryptedTokenProvider
{
    use CryptTrait;

    /**
     * @param string $token
     *
     * @return array<string,string|int>|null. E.g. for refresh token:
     *  [
     *      'refresh_token_id' => 'refresh token identifier',
     *      'client_id' => 'client identifier',
     *      'expire_time' => 1234567890, // expiration time, UNIX timestamp
     *      'user_id' => 'user identifier',
     *  ]
     */
    public function getDecryptedToken(string $token): ?array
    {
        try {
            return json_decode($this->decrypt($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $throwable) {
            return null;
        }
    }
}
