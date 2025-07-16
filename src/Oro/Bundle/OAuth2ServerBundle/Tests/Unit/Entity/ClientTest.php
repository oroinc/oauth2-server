<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientTest extends TestCase
{
    public function testGetId(): void
    {
        $entity = new Client();
        self::assertNull($entity->getId());

        $id = 123;
        ReflectionUtil::setId($entity, $id);
        self::assertSame($id, $entity->getId());
    }

    public function testName(): void
    {
        $name = 'test';

        $entity = new Client();
        self::assertNull($entity->getName());

        $entity->setName($name);
        self::assertSame($name, $entity->getName());
    }

    public function testIdentifier(): void
    {
        $identifier = 'test';

        $entity = new Client();
        self::assertNull($entity->getIdentifier());

        $entity->setIdentifier($identifier);
        self::assertSame($identifier, $entity->getIdentifier());
    }

    public function testSecret(): void
    {
        $secret = 'test_secret';
        $salt = 'test_salt';

        $entity = new Client();
        self::assertNull($entity->getSecret());
        self::assertNull($entity->getSalt());

        $entity->setSecret($secret, $salt);
        self::assertSame($secret, $entity->getSecret());
        self::assertSame($salt, $entity->getSalt());
    }

    public function testPlainSecret(): void
    {
        $plainSecret = 'test';

        $entity = new Client();
        self::assertNull($entity->getPlainSecret());

        $entity->setPlainSecret($plainSecret);
        self::assertSame($plainSecret, $entity->getPlainSecret());
    }

    public function testGrants(): void
    {
        $grants = ['test'];

        $entity = new Client();
        self::assertNull($entity->getGrants());

        $entity->setGrants($grants);
        self::assertSame($grants, $entity->getGrants());
    }

    public function testScopes(): void
    {
        $scopes = ['test'];

        $entity = new Client();
        self::assertNull($entity->getScopes());

        $entity->setScopes($scopes);
        self::assertSame($scopes, $entity->getScopes());
    }

    public function testRedirectUris(): void
    {
        $uris = ['test'];

        $entity = new Client();
        self::assertNull($entity->getRedirectUris());

        $entity->setRedirectUris($uris);
        self::assertSame($uris, $entity->getRedirectUris());
    }

    public function testActive(): void
    {
        $entity = new Client();
        self::assertTrue($entity->isActive());

        $entity->setActive(false);
        self::assertFalse($entity->isActive());

        $entity->setActive(true);
        self::assertTrue($entity->isActive());
    }

    public function testOrganization(): void
    {
        $organization = $this->createMock(Organization::class);

        $entity = new Client();
        self::assertNull($entity->getOrganization());

        $entity->setOrganization($organization);
        self::assertSame($organization, $entity->getOrganization());

        $entity->setOrganization(null);
        self::assertNull($entity->getOrganization());
    }

    public function testOwnerEntity(): void
    {
        $ownerEntityClass = 'Test\Entity';
        $ownerEntityId = 123;

        $entity = new Client();
        self::assertNull($entity->getOwnerEntityClass());
        self::assertNull($entity->getOwnerEntityId());

        $entity->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        self::assertSame($ownerEntityClass, $entity->getOwnerEntityClass());
        self::assertSame($ownerEntityId, $entity->getOwnerEntityId());
    }

    public function testFrontend(): void
    {
        $entity = new Client();
        self::assertFalse($entity->isFrontend());

        $entity->setFrontend(true);
        self::assertTrue($entity->isFrontend());

        $entity->setFrontend(false);
        self::assertFalse($entity->isFrontend());
    }

    public function testLastUsedAt(): void
    {
        $entity = new Client();
        self::assertNull($entity->getLastUsedAt());

        $lastUsedAt = new \DateTime();
        $entity->setLastUsedAt($lastUsedAt);
        self::assertSame($lastUsedAt, $entity->getLastUsedAt());
    }

    public function testConfidential(): void
    {
        $entity = new Client();
        self::assertTrue($entity->isConfidential());

        $entity->setConfidential(false);
        self::assertFalse($entity->isConfidential());

        $entity->setConfidential(true);
        self::assertTrue($entity->isConfidential());
    }

    public function testPlainTextPkceAllowed(): void
    {
        $entity = new Client();
        self::assertFalse($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(true);
        self::assertTrue($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(false);
        self::assertFalse($entity->isPlainTextPkceAllowed());
    }
}
