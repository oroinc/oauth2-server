<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Token;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Token\UsernamePasswordOrganizationToken;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class SessionTransferAuthenticationTokenTest extends TestCase
{
    public function testTokenRepresentsSessionTransferAuthentication(): void
    {
        $user = new User();
        $user->setUsername('user@example.com');
        $organization = new Organization();
        $organization->setName('Organization');

        $token = new SessionTransferAuthenticationToken(
            $user,
            'main',
            $organization
        );

        self::assertInstanceOf(UsernamePasswordOrganizationToken::class, $token);
        self::assertSame($user, $token->getUser());
        self::assertSame($organization, $token->getOrganization());
        self::assertSame('main', $token->getFirewallName());

        $restoredToken = unserialize(serialize($token));

        self::assertInstanceOf(SessionTransferAuthenticationToken::class, $restoredToken);
        self::assertSame('user@example.com', $restoredToken->getUserIdentifier());
        self::assertSame('Organization', $restoredToken->getOrganization()->getName());
    }
}
