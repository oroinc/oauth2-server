<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity;

use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;

class AuthCodeTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $identifier = 'test_id';
        $expiresAt = new \DateTime();
        $scopes = ['test_scope'];
        $client = $this->createMock(Client::class);
        $userIdentifier = 'test_user';

        $entity = new AuthCode(
            $identifier,
            $expiresAt,
            $scopes,
            $client,
            $userIdentifier
        );

        self::assertSame($identifier, $entity->getIdentifier());
        self::assertSame($expiresAt, $entity->getExpiresAt());
        self::assertSame($scopes, $entity->getScopes());
        self::assertSame($client, $entity->getClient());
        self::assertSame($userIdentifier, $entity->getUserIdentifier());
    }

    public function testUserIdentifierCanBeNull(): void
    {
        $entity = new AuthCode(
            'test_id',
            new \DateTime(),
            ['test_scope'],
            $this->createMock(Client::class),
            null
        );

        self::assertNull($entity->getUserIdentifier());
    }

    public function testRevoke(): void
    {
        $entity = new AuthCode(
            'test_id',
            new \DateTime(),
            ['test_scope'],
            $this->createMock(Client::class),
            'test_user'
        );

        self::assertFalse($entity->isRevoked());

        $entity->revoke();
        self::assertTrue($entity->isRevoked());
    }

    public function testGetId(): void
    {
        $entity = new AuthCode(
            'test_id',
            new \DateTime(),
            ['test_scope'],
            $this->createMock(Client::class),
            'test_user'
        );
        self::assertNull($entity->getId());

        $id = 123;
        ReflectionUtil::setId($entity, $id);
        self::assertSame($id, $entity->getId());
    }
}
