<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The entity for OAuth 2.0 refresh token.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *    name="oro_oauth2_refresh_token",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="oro_oauth2_refresh_token_uidx", columns={"identifier"})
 *    }
 * )
 */
class RefreshToken
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="identifier", type="string", length=80)
     */
    private $identifier;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expires_at", type="datetime")
     */
    private $expiresAt;

    /**
     * @var AccessToken
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken")
     * @ORM\JoinColumn(name="access_token_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $accessToken;

    /**
     * @var bool
     *
     * @ORM\Column(name="revoked", type="boolean", options={"default"=false})
     */
    private $revoked = false;

    public function __construct(
        string $identifier,
        \DateTime $expiresAt,
        AccessToken $accessToken
    ) {
        $this->identifier = $identifier;
        $this->expiresAt = $expiresAt;
        $this->accessToken = $accessToken;
    }

    /**
     * Gets the entity identifier.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Gets a string that unique identifies the token.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Gets the date when the token expires.
     */
    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Gets the access token this refresh token belongs to.
     */
    public function getAccessToken(): AccessToken
    {
        return $this->accessToken;
    }

    /**
     * Indicates whether the token has been revoked or not.
     */
    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    /**
     * Revokes the token.
     */
    public function revoke()
    {
        $this->revoked = true;
    }
}
