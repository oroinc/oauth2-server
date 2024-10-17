<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken as RefreshTokenEntity;

/**
 * Decrypts a refresh token, loads {@see RefreshTokenEntity} from database.
 */
class RefreshTokenDataProvider
{
    public function __construct(
        private DecryptedTokenProvider $decryptedTokenProvider,
        private ManagerRegistry $doctrine
    ) {
    }

    /**
     * @param string $refreshToken
     *
     * @return array<string,string|int>|null
     *  [
     *      'refresh_token_id' => 'refresh token identifier',
     *      'client_id' => 'client identifier',
     *      'expire_time' => 1234567890, // expiration time, UNIX timestamp
     *      'user_id' => 'user identifier',
     *  ]
     */
    public function getRefreshTokenData(#[\SensitiveParameter] string $refreshToken): ?array
    {
        $decryptedToken = $this->decryptedTokenProvider->getDecryptedToken($refreshToken);
        if (!$decryptedToken) {
            // The token cannot be decrypted.
            return null;
        }

        return $decryptedToken;
    }

    public function getRefreshTokenEntity(#[\SensitiveParameter] string $refreshToken): ?RefreshTokenEntity
    {
        $decryptedToken = $this->decryptedTokenProvider->getDecryptedToken($refreshToken);

        if (empty($decryptedToken['refresh_token_id'])) {
            // The decrypted token does not contain an identifier.
            return null;
        }

        return $this->doctrine
            ->getRepository(RefreshTokenEntity::class)
            ->findOneBy(['identifier' => $decryptedToken['refresh_token_id']]);
    }
}
