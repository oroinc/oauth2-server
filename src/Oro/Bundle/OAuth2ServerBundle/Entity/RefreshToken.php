<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The entity for OAuth 2.0 refresh token.
 */
#[ORM\Entity]
#[ORM\Table(name: 'oro_oauth2_refresh_token')]
#[ORM\UniqueConstraint(name: 'oro_oauth2_refresh_token_uidx', columns: ['identifier'])]
class RefreshToken
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'identifier', type: Types::STRING, length: 80)]
    private ?string $identifier;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiresAt;

    #[ORM\ManyToOne(targetEntity: AccessToken::class)]
    #[ORM\JoinColumn(name: 'access_token_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?AccessToken $accessToken;

    #[ORM\Column(name: 'revoked', type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $revoked = false;

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
