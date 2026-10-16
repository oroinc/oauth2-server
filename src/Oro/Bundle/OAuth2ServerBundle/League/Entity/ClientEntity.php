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
    use EntityTrait;
    use ClientTrait;

    /** @var bool */
    private $frontend = false;

    /** @var bool */
    private $plainTextPkceAllowed = false;

    /** @var bool */
    private $skipAuthorizeClientAllowed = false;

    /** @var array */
    private $apis = [];

    private bool $logoutAllowed = false;

    /** @var string[] */
    private array $postLogoutRedirectUris = [];

    /** @var string[] */
    private array $grants = [];

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

    public function isSkipAuthorizeClientAllowed(): bool
    {
        return $this->skipAuthorizeClientAllowed;
    }

    public function setSkipAuthorizeClientAllowed(bool $skipAuthorizeClientAllowed): void
    {
        $this->skipAuthorizeClientAllowed = $skipAuthorizeClientAllowed;
    }

    public function getApis(): array
    {
        return $this->apis;
    }

    public function setApis(array $apis): void
    {
        $this->apis = $apis;
    }

    public function isLogoutAllowed(): bool
    {
        return $this->logoutAllowed;
    }

    public function setLogoutAllowed(bool $logoutAllowed): void
    {
        $this->logoutAllowed = $logoutAllowed;
    }

    /**
     * @return string[]
     */
    public function getPostLogoutRedirectUris(): array
    {
        return $this->postLogoutRedirectUris;
    }

    /**
     * @param string[] $postLogoutRedirectUris
     */
    public function setPostLogoutRedirectUris(array $postLogoutRedirectUris): void
    {
        $this->postLogoutRedirectUris = $postLogoutRedirectUris;
    }

    /**
     * Gets grant types supported by the client.
     *
     * @return string[]
     */
    public function getGrants(): array
    {
        return $this->grants;
    }

    /**
     * @param string[] $grants
     */
    public function setGrants(array $grants): void
    {
        $this->grants = $grants;
    }
}
