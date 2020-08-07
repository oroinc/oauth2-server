<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The entity for OAuth 2.0 access token.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *    name="oro_oauth2_access_token",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="oro_oauth2_access_token_uidx", columns={"identifier"})
 *    }
 * )
 */
class AccessToken
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
        string $userIdentifier = null
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
     * Gets a string that unique identifies the token.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Gets the date when the token expires.
     *
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Gets scopes the token grants access to.
     *
     * @return string[]
     */
    public function getScopes()
    {
        return $this->scopes ?? [];
    }

    /**
     * Gets the client the token is issued to.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Gets a string that identifies the user represented by the token.
     *
     * @return string|null
     */
    public function getUserIdentifier()
    {
        return $this->userIdentifier;
    }

    /**
     * Indicates whether the token has been revoked or not.
     *
     * @return bool
     */
    public function isRevoked()
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
