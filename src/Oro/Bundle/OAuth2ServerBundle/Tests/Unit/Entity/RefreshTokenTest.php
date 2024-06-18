<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity;

use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Component\Testing\ReflectionUtil;

class RefreshTokenTest extends \PHPUnit\Framework\TestCase
{
    public function testGetId()
    {
        $entity = new RefreshToken('testId', new \DateTime(), $this->createMock(AccessToken::class));
        ReflectionUtil::setId($entity, 123);
        self::assertEquals(123, $entity->getId());
    }

    public function testGetIdentifier()
    {
        $entity = new RefreshToken('testId', new \DateTime(), $this->createMock(AccessToken::class));
        self::assertEquals('testId', $entity->getIdentifier());
    }

    public function testGetExpiresAt()
    {
        $expiresAt = new \DateTime();
        $entity = new RefreshToken('testId', $expiresAt, $this->createMock(AccessToken::class));
        self::assertEquals($expiresAt, $entity->getExpiresAt());
    }

    public function testGetAccessToken()
    {
        $accessToken = $this->createMock(AccessToken::class);
        $entity = new RefreshToken('testId', new \DateTime(), $accessToken);
        self::assertEquals($accessToken, $entity->getAccessToken());
    }

    public function testRevoke()
    {
        $entity = new RefreshToken('testId', new \DateTime(), $this->createMock(AccessToken::class));
        self::assertFalse($entity->isRevoked());
        $entity->revoke();
        self::assertTrue($entity->isRevoked());
    }
}
