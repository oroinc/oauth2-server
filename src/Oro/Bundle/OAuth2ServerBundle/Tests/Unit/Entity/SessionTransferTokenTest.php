<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;

class SessionTransferTokenTest extends TestCase
{
    public function testAccessors(): void
    {
        $client = new Client();
        $organization = new Organization();
        $createdAt = new \DateTime('2026-08-03 12:00:00 UTC');
        $expiresAt = new \DateTime('2026-08-03 12:01:00 UTC');
        $consumedAt = new \DateTime('2026-08-03 12:00:30 UTC');
        $token = new SessionTransferToken();
        ReflectionUtil::setId($token, 42);

        self::assertSame($token, $token->setIdentifier('hashed-token'));
        self::assertSame($token, $token->setClient($client));
        self::assertSame($token, $token->setSourceAccessTokenIdentifier('access-token'));
        self::assertSame($token, $token->setUserIdentifier('user-id'));
        self::assertSame($token, $token->setOrganization($organization));
        self::assertSame($token, $token->setRoute('target_route'));
        self::assertSame($token, $token->setRouteParameters(['id' => 10]));
        self::assertSame($token, $token->setContextData(['website_id' => 20]));
        self::assertSame($token, $token->setCreatedAt($createdAt));
        self::assertSame($token, $token->setExpiresAt($expiresAt));
        self::assertSame($token, $token->setConsumedAt($consumedAt));
        self::assertSame($token, $token->setRevoked(true));

        self::assertSame(42, $token->getId());
        self::assertSame('hashed-token', $token->getIdentifier());
        self::assertSame($client, $token->getClient());
        self::assertSame('access-token', $token->getSourceAccessTokenIdentifier());
        self::assertSame('user-id', $token->getUserIdentifier());
        self::assertSame($organization, $token->getOrganization());
        self::assertSame('target_route', $token->getRoute());
        self::assertSame(['id' => 10], $token->getRouteParameters());
        self::assertSame(['website_id' => 20], $token->getContextData());
        self::assertSame($createdAt, $token->getCreatedAt());
        self::assertSame($expiresAt, $token->getExpiresAt());
        self::assertSame($consumedAt, $token->getConsumedAt());
        self::assertTrue($token->isConsumed());
        self::assertTrue($token->isRevoked());
    }

    public function testLifecycleState(): void
    {
        $now = new \DateTimeImmutable('2026-08-03 12:00:00 UTC');
        $token = (new SessionTransferToken())
            ->setExpiresAt($now->modify('+1 minute'));

        self::assertFalse($token->isExpired($now));
        self::assertTrue($token->isUsable($now));

        self::assertSame($token, $token->consume($now));
        self::assertFalse($token->isUsable($now));

        $token->setConsumedAt(null);
        self::assertSame($token, $token->revoke());
        self::assertFalse($token->isUsable($now));
    }

    public function testTokenWithoutExpirationIsExpired(): void
    {
        $token = new SessionTransferToken();

        self::assertTrue($token->isExpired());
        self::assertFalse($token->isUsable());
    }

    public function testTokenExpiringAtCurrentTimeIsExpired(): void
    {
        $now = new \DateTimeImmutable('2026-08-03 12:00:00 UTC');
        $token = (new SessionTransferToken())->setExpiresAt($now);

        self::assertTrue($token->isExpired($now));
        self::assertFalse($token->isUsable($now));
    }
}
