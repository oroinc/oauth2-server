<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;

/**
 * The helper class that can be used to check existence of files contain private and public keys
 * that are used to sign and verify JWT tokens.
 */
class EncryptionKeysExistenceChecker
{
    public const string PRIVATE_KEY_PERMISSION = '0600';

    public function __construct(
        private CryptKeyFile $privateKey,
        private CryptKeyFile $publicKey
    ) {
    }

    /**
     * Checks whether the private key that is used to sign JWT tokens exists and readable.
     */
    public function isPrivateKeyExist(): bool
    {
        return $this->isFileExistsAndReadable($this->privateKey->getKeyPath());
    }

    /**
     * Checks whether the public key that is used to verify JWT tokens exists and readable.
     */
    public function isPublicKeyExist(): bool
    {
        return $this->isFileExistsAndReadable($this->publicKey->getKeyPath());
    }

    /**
     * Checks whether the permission for the private key is equal to 0600.
     *
     * @return bool|null True if mode is 0600, false otherwise. Returns null if private key does not exist.
     */
    public function isPrivateKeySecure(): ?bool
    {
        if (!file_exists($this->privateKey->getKeyPath())) {
            return null;
        }

        return substr(decoct(fileperms($this->privateKey->getKeyPath())), -4) === static::PRIVATE_KEY_PERMISSION;
    }

    private function isFileExistsAndReadable(string $file): bool
    {
        return file_exists($file) && is_readable($file);
    }
}
