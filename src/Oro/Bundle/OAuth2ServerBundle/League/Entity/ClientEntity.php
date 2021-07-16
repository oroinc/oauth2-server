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

    public function isFrontend(): bool
    {
        return $this->frontend;
    }

    public function setFrontend(bool $frontend): void
    {
        $this->frontend = $frontend;
    }

    public function setConfidential(bool $confidential): void
    {
        $this->isConfidential = $confidential;
    }

    public function isPlainTextPkceAllowed(): bool
    {
        return $this->plainTextPkceAllowed;
    }

    public function setPlainTextPkceAllowed(bool $plainTextPkceAllowed): void
    {
        $this->plainTextPkceAllowed = $plainTextPkceAllowed;
    }
}
