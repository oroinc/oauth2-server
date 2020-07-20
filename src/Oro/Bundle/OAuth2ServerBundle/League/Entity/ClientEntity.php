<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * The implementation of the client entity for "league/oauth2-server" library.
 */
class ClientEntity implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;

    /** @var bool */
    private $frontend = false;

    /** @var bool */
    private $plainTextPkceAllowed = false;

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param string|string[] $redirectUri
     */
    public function setRedirectUri($redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    /**
     * @return bool
     */
    public function isFrontend(): bool
    {
        return $this->frontend;
    }

    /**
     * @param bool $frontend
     */
    public function setFrontend(bool $frontend): void
    {
        $this->frontend = $frontend;
    }

    /**
     * @param bool $confidential
     */
    public function setConfidential(bool $confidential): void
    {
        $this->isConfidential = $confidential;
    }

    /**
     * @return bool
     */
    public function isPlainTextPkceAllowed(): bool
    {
        return $this->plainTextPkceAllowed;
    }

    /**
     * @param bool $plainTextPkceAllowed
     */
    public function setPlainTextPkceAllowed(bool $plainTextPkceAllowed): void
    {
        $this->plainTextPkceAllowed = $plainTextPkceAllowed;
    }
}
