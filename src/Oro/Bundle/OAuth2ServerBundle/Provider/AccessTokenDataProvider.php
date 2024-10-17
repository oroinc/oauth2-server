<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken as AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;

/**
 * Decrypts an access token, loads {@see AccessTokenEntity} from database.
 */
class AccessTokenDataProvider
{
    public function __construct(
        private DecryptedAccessTokenProvider $decryptedAccessTokenProvider,
        private ManagerRegistry $doctrine
    ) {
    }

    /**
     * @return array<string,string|array>|null
     *  [
     *      'jti' => 'token identifier',
     *      'aud' => ['client identifier'],
     *      'sub' => 'admin', // user identifier
     *      'scopes' => ['all'], // OAuth2 scopes
     *      'exp' => \DateTimeImmutable $dateTime, // expires at
     *      'iat' => \DateTimeImmutable $dateTime, // issued at
     *      'nbf' => \DateTimeImmutable $dateTime, // not valid before
     *  ]
     *
     * @throws CryptKeyNotFoundException
     */
    public function getAccessTokenData(#[\SensitiveParameter] string $accessToken): ?array
    {
        $decryptedToken = $this->decryptedAccessTokenProvider->getDecryptedAccessToken($accessToken);

        return $decryptedToken?->claims()->all();
    }

    /**
     * @throws CryptKeyNotFoundException
     */
    public function getAccessTokenEntity(#[\SensitiveParameter] string $accessToken): ?AccessTokenEntity
    {
        $decryptedToken = $this->getAccessTokenData($accessToken);
        if (empty($decryptedToken['jti'])) {
            // The decrypted token does not contain an identifier.
            return null;
        }

        return $this->doctrine
            ->getRepository(AccessTokenEntity::class)
            ->findOneBy(['identifier' => $decryptedToken['jti']]);
    }
}
