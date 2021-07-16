<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;

/**
 * The helper class that can be used to check existence of files contain private and public keys
 * that are used to sign and verify JWT tokens.
 */
class EncryptionKeysExistenceChecker
{
    /** @var CryptKeyFile */
    private $privateKey;

    /** @var CryptKeyFile */
    private $publicKey;

    public function __construct(CryptKeyFile $privateKey, CryptKeyFile $publicKey)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    /**
     * Checks whether the private key that is used to sign JWT tokens exists and readable.
     */
    public function isPrivateKeyExist(): bool
    {
        return $this->isFileExistAndReadable($this->privateKey->getKeyPath());
    }

    /**
     * Checks whether the public key that is used to verify JWT tokens exists and readable.
     */
    public function isPublicKeyExist(): bool
    {
        return $this->isFileExistAndReadable($this->publicKey->getKeyPath());
    }

    private function isFileExistAndReadable(string $file): bool
    {
        return file_exists($file) && is_readable($file);
    }
}
