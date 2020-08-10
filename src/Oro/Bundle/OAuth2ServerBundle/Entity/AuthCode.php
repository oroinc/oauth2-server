<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The entity for OAuth 2.0 authorization code.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *    name="oro_oauth2_auth_code",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="oro_oauth2_auth_code_uidx", columns={"identifier"})
 *    }
 * )
 */
class AuthCode
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
     * @var string[]
     *
     * @ORM\Column(name="scopes", type="simple_array", nullable=true)
     */
    private $scopes;

    /**
     * @var Client
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OAuth2ServerBundle\Entity\Client")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $client;

    /**
     * @var string|null
     *
     * @ORM\Column(name="user_identifier", type="string", length=128, nullable=true)
     */
    private $userIdentifier;

    /**
     * @var bool
     *
     * @ORM\Column(name="revoked", type="boolean", options={"default"=false})
     */
    private $revoked = false;

    /**
     * @param string      $identifier
     * @param \DateTime   $expiresAt
     * @param string[]    $scopes
     * @param Client      $client
     * @param string|null $userIdentifier
     */
    public function __construct(
        string $identifier,
        \DateTime $expiresAt,
        array $scopes,
        Client $client,
        ?string $userIdentifier
    ) {
        $this->identifier = $identifier;
        $this->expiresAt = $expiresAt;
        $this->scopes = $scopes ?: null;
        $this->client = $client;
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * Gets the entity identifier.
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets a string that unique identifies the authorization code.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Gets the date when the authorization code expires.
     *
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Gets scopes the authorization code grants access to.
     *
     * @return string[]
     */
    public function getScopes()
    {
        return $this->scopes ?? [];
    }

    /**
     * Gets the client the authorization code is issued to.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Gets a string that identifies the user represented by the authorization code.
     *
     * @return string|null
     */
    public function getUserIdentifier()
    {
        return $this->userIdentifier;
    }

    /**
     * Indicates whether the authorization code has been revoked or not.
     *
     * @return bool
     */
    public function isRevoked()
    {
        return $this->revoked;
    }

    /**
     * Revokes the authorization code.
     */
    public function revoke()
    {
        $this->revoked = true;
    }
}
