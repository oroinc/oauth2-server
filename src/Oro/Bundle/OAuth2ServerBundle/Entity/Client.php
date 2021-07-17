<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

/**
 * The entity for OAuth 2.0 client (another name is OAuth 2.0 application).
 *
 * @ORM\Entity()
 * @ORM\Table(
 *      name="oro_oauth2_client",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="oro_oauth2_client_uidx", columns={"identifier"})
 *      },
 *      indexes={
 *          @ORM\Index(name="oro_oauth2_client_owner_idx", columns={"owner_entity_class", "owner_entity_id"})
 *      }
 * )
 * @Config(
 *  defaultValues={
 *      "ownership"={
 *          "owner_type"="ORGANIZATION",
 *          "owner_field_name"="organization",
 *          "owner_column_name"="organization_id"
 *      },
 *      "security"={
 *          "type"="ACL",
 *          "permissions"="VIEW;CREATE;EDIT;DELETE",
 *          "group_name"=""
 *      }
 *  }
 * )
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Client
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="identifier", type="string", length=32)
     */
    private $identifier;

    /**
     * @var string
     *
     * @ORM\Column(name="secret", type="string", length=128, nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "email"={
     *              "available_in_template"=false
     *          }
     *      }
     * )
     */
    private $secret;

    /**
     * @var string
     *
     * @ORM\Column(name="salt", type="string", length=50)
     * @ConfigField(
     *      defaultValues={
     *          "email"={
     *              "available_in_template"=false
     *          }
     *      }
     * )
     */
    private $salt;

    /**
     * This property is used to share the original value of the secret
     * between services involved in this entity creation.
     * Must not be persisted.
     *
     * @var string
     */
    private $plainSecret;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="last_used_at", type="datetime", nullable=true)
     */
    private $lastUsedAt;

    /**
     * @var string[]
     *
     * @ORM\Column(name="grants", type="simple_array")
     */
    private $grants;

    /**
     * @var string[]|null
     *
     * @ORM\Column(name="scopes", type="simple_array", nullable=true)
     */
    private $scopes;

    /**
     * @var string[]|null
     *
     * @ORM\Column(name="redirect_uris", type="simple_array", nullable=true)
     */
    private $redirectUris;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", options={"default"=true})
     */
    private $active = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="frontend", type="boolean", options={"default"=false})
     */
    private $frontend = false;

    /**
     * @var Organization
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OrganizationBundle\Entity\Organization")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $organization;

    /**
     * @var string
     *
     * @ORM\Column(name="owner_entity_class", type="string", length=255, nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "email"={
     *              "available_in_template"=false
     *          }
     *      }
     * )
     */
    private $ownerEntityClass;

    /**
     * @var int
     *
     * @ORM\Column(name="owner_entity_id", type="integer", nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "email"={
     *              "available_in_template"=false
     *          }
     *      }
     * )
     */
    private $ownerEntityId;

    /**
     * @var bool
     *
     * @ORM\Column(name="confidential", type="boolean", options={"default"=true})
     */
    private $confidential = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="plain_text_pkce_allowed", type="boolean", options={"default"=false})
     */
    private $plainTextPkceAllowed = false;

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
     * Gets the human-readable name of the client.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the human-readable name of the client.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets a string that unique identifies the client.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Sets a string that unique identifies the client.
     *
     * @param string $identifier
     *
     * @return $this
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Gets a hash of the secret that is used for the client authentication.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Sets a hash of the secret that is used for the client authentication.
     *
     * @param string $secret The encoded secret
     * @param string $salt   The salt that was used to encode the secret
     *
     * @return $this
     */
    public function setSecret(string $secret, string $salt)
    {
        $this->secret = $secret;
        $this->salt = $salt;

        return $this;
    }

    /**
     * Gets the salt that was used to encode the secret.
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Gets the original value of the secret that is used for the client authentication.
     *
     * @return string
     */
    public function getPlainSecret()
    {
        return $this->plainSecret;
    }

    /**
     * Sets the original value of the secret that is used for the client authentication.
     *
     * @param string $secret
     *
     * @return $this
     */
    public function setPlainSecret($secret)
    {
        $this->plainSecret = $secret;

        return $this;
    }

    /**
     * Gets grant types supported by the client.
     *
     * @return string[]
     */
    public function getGrants()
    {
        return $this->grants;
    }

    /**
     * Sets grant types supported by the client.
     *
     * @param string[] $grants
     *
     * @return $this
     */
    public function setGrants($grants)
    {
        $this->grants = $grants;

        return $this;
    }

    /**
     * Gets scopes to which the client has access.
     *
     * @return string[]
     */
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * Sets scopes to which the client has access.
     *
     * @param string[] $scopes
     *
     * @return $this
     */
    public function setScopes($scopes)
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Gets URIs to which it is allowed to redirect the user back to.
     *
     * @return string[]
     */
    public function getRedirectUris()
    {
        return $this->redirectUris;
    }

    /**
     * Sets URIs to which it is allowed to redirect the user back to.
     *
     * @param string[] $redirectUris
     *
     * @return $this
     */
    public function setRedirectUris($redirectUris)
    {
        $this->redirectUris = $redirectUris;

        return $this;
    }

    /**
     * Indicates whether the client is allowed to access resources or not.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Sets a flag indicates whether the client is allowed to access resources or not.
     *
     * @param bool $active
     *
     * @return $this
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Gets the organization the client issued to.
     *
     * @return Organization
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * Sets the organization the client issued to.
     *
     * @param Organization|null $organization
     *
     * @return $this
     */
    public function setOrganization(Organization $organization = null)
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * Gets the class name of the entity the client belongs to.
     *
     * @return string
     */
    public function getOwnerEntityClass()
    {
        return $this->ownerEntityClass;
    }

    /**
     * Gets ID of the entity the client belongs to.
     *
     * @return int
     */
    public function getOwnerEntityId()
    {
        return $this->ownerEntityId;
    }

    /**
     * Sets the entity the client belongs to.
     *
     * @param string $entityClass
     * @param int    $entityId
     *
     * @return $this
     */
    public function setOwnerEntity(string $entityClass, int $entityId)
    {
        $this->ownerEntityClass = $entityClass;
        $this->ownerEntityId = $entityId;

        return $this;
    }

    /**
     * Indicates whether the client is intended to be used on storefront or management console.
     *
     * @return bool
     */
    public function isFrontend()
    {
        return $this->frontend;
    }

    /**
     * Sets the flag that indicates whether the client is intended to be used on storefront or management console.
     */
    public function setFrontend(bool $frontend)
    {
        $this->frontend = $frontend;
    }

    public function getLastUsedAt(): ?\DateTime
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTime $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }

    /**
     * Indicates whether the client is a confidential or a public.
     * @link https://tools.ietf.org/html/rfc6749#section-2.1
     *
     * @return bool
     */
    public function isConfidential()
    {
        return $this->confidential;
    }

    /**
     * Sets the flag that indicates whether the client is a confidential or a public.
     * @link https://tools.ietf.org/html/rfc6749#section-2.1
     */
    public function setConfidential(bool $confidential)
    {
        $this->confidential = $confidential;
    }

    /**
     * Indicates whether the PKCE code challenge can be send as a plain text.
     * @link https://tools.ietf.org/html/rfc7636#section-4.3
     *
     * @return bool
     */
    public function isPlainTextPkceAllowed()
    {
        return $this->plainTextPkceAllowed;
    }

    /**
     * Sets the flag that indicates whether the PKCE code challenge can be send as a plain text.
     * @link https://tools.ietf.org/html/rfc7636#section-4.3
     */
    public function setPlainTextPkceAllowed(bool $plainTextPkceAllowed)
    {
        $this->plainTextPkceAllowed = $plainTextPkceAllowed;
    }
}
