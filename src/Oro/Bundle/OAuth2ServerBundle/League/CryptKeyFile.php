<?php

namespace Oro\Bundle\OAuth2ServerBundle\League;

/**
 * Represents the file path of a crypt key.
 */
class CryptKeyFile
{
    /** @var string */
    private $keyPath;

    /**
     * @param string $keyPath
     */
    public function __construct(string $keyPath)
    {
        $this->keyPath = $keyPath;
    }

    /**
     * Gets the path to the crypt key file.
     *
     * @return string
     */
    public function getKeyPath(): string
    {
        return $this->keyPath;
    }
}
