<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

/**
 * Stores data for a short-lived, one-time Session Transfer Token.
 *
 * The raw token is never stored. The identifier contains a SHA-256 hash
 * of the raw token returned to the OAuth client.
 */
#[ORM\Entity]
#[ORM\Table(name: 'oro_oauth2_session_transfer_token')]
#[ORM\UniqueConstraint(name: 'oro_oauth2_stt_uidx', columns: ['identifier'])]
#[ORM\Index(columns: ['expires_at'], name: 'oro_oauth2_stt_exp_idx')]
#[ORM\Index(columns: ['consumed_at'], name: 'oro_oauth2_stt_consumed_idx')]
#[ORM\Index(columns: ['source_access_token_identifier'], name: 'oro_oauth2_stt_source_idx')]
class SessionTransferToken
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    /**
     * SHA-256 hash of the raw Session Transfer Token.
     */
    #[ORM\Column(name: 'identifier', type: Types::STRING, length: 64)]
    private ?string $identifier = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(name: 'source_access_token_identifier', type: Types::STRING, length: 80)]
    private ?string $sourceAccessTokenIdentifier = null;

    #[ORM\Column(name: 'user_identifier', type: Types::STRING, length: 128)]
    private ?string $userIdentifier = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\Column(name: 'route', type: Types::STRING, length: 255)]
    private ?string $route = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'route_parameters', type: Types::JSON)]
    private array $routeParameters = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(name: 'context_data', type: Types::JSON)]
    private array $contextData = [];

    #[ORM\Column(name: 'consumed_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $consumedAt = null;

    #[ORM\Column(name: 'revoked', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $revoked = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getSourceAccessTokenIdentifier(): ?string
    {
        return $this->sourceAccessTokenIdentifier;
    }

    public function setSourceAccessTokenIdentifier(string $sourceAccessTokenIdentifier): self
    {
        $this->sourceAccessTokenIdentifier = $sourceAccessTokenIdentifier;

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): self
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function setRouteParameters(array $routeParameters): self
    {
        $this->routeParameters = $routeParameters;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getConsumedAt(): ?\DateTimeInterface
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeInterface $consumedAt): self
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function consume(\DateTimeInterface $consumedAt): self
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function revoke(): self
    {
        $this->revoked = true;

        return $this;
    }

    public function getContextData(): array
    {
        return $this->contextData;
    }

    public function setContextData(array $contextData): self
    {
        $this->contextData = $contextData;

        return $this;
    }

    public function isExpired(?\DateTimeInterface $now = null): bool
    {
        if (null === $this->expiresAt) {
            return true;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->expiresAt <= $now;
    }

    public function isUsable(?\DateTimeInterface $now = null): bool
    {
        return !$this->revoked
            && !$this->isConsumed()
            && !$this->isExpired($now);
    }
}
