<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\CryptKey;
use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decrypts an access token.
 */
class DecryptedAccessTokenProvider implements ResetInterface
{
    private ?CryptKey $publicKey = null;
    private ?Configuration $jwtConfiguration = null;

    public function __construct(
        private CryptKeyFile $publicKeyFile
    ) {
    }

    #[\Override]
    public function reset(): void
    {
        $this->jwtConfiguration = null;
        $this->publicKey = null;
    }

    /**
     * @throws CryptKeyNotFoundException
     */
    public function getDecryptedAccessToken(#[\SensitiveParameter] string $accessToken): ?Token
    {
        $jwtConfiguration = $this->getJwtConfiguration();

        try {
            $decryptedToken = $jwtConfiguration->parser()->parse($accessToken);
        } catch (\Lcobucci\JWT\Exception $e) {
            // The token cannot be decrypted.
            return null;
        }

        return $decryptedToken;
    }

    /**
     * @throws CryptKeyNotFoundException
     */
    private function getJwtConfiguration(): Configuration
    {
        if (null === $this->jwtConfiguration) {
            $this->jwtConfiguration = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText('empty', 'empty')
            );

            $publicKey = $this->getPublicKey();

            $this->jwtConfiguration->setValidationConstraints(
                new SignedWith(
                    new Sha256(),
                    InMemory::plainText($publicKey->getKeyContents(), $publicKey->getPassPhrase() ?? '')
                )
            );
        }

        return $this->jwtConfiguration;
    }

    /**
     * @throws CryptKeyNotFoundException
     */
    private function getPublicKey(): CryptKey
    {
        if (null === $this->publicKey) {
            try {
                $this->publicKey = new CryptKey($this->publicKeyFile->getKeyPath(), null, false);
            } catch (\LogicException $e) {
                throw CryptKeyNotFoundException::create($e);
            }
        }

        return $this->publicKey;
    }
}
