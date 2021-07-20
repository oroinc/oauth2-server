<?php

namespace Oro\Bundle\OAuth2ServerBundle\League;

/**
 * Represents the file path of a crypt key.
 */
class CryptKeyFile
{
    /** @var string */
    private $keyPath;

    public function __construct(string $keyPath)
    {
        $this->keyPath = $keyPath;
    }

    /**
     * Gets the path to the crypt key file.
     */
    public function getKeyPath(): string
    {
        return $this->keyPath;
    }
}
