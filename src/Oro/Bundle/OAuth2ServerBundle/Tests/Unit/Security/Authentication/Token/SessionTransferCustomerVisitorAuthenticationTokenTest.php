<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Token;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use PHPUnit\Framework\TestCase;

class SessionTransferCustomerVisitorAuthenticationTokenTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!\class_exists('Oro\\Bundle\\CustomerBundle\\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
    }

    public function testTokenRepresentsSessionTransferCustomerVisitorAuthentication(): void
    {
        $visitor = (new CustomerVisitor())->setSessionId('visitor-session-id');
        $organization = (new Organization())->setName('Organization');
        $token = new SessionTransferCustomerVisitorAuthenticationToken(
            $visitor,
            ['ROLE_FRONTEND_ANONYMOUS'],
            $organization
        );

        self::assertSame($visitor, $token->getVisitor());
        self::assertSame($organization, $token->getOrganization());
        self::assertSame(['ROLE_FRONTEND_ANONYMOUS'], $token->getRoleNames());

        $restoredToken = unserialize(serialize($token));

        self::assertInstanceOf(SessionTransferCustomerVisitorAuthenticationToken::class, $restoredToken);
        self::assertSame('visitor-session-id', $restoredToken->getVisitor()->getSessionId());
        self::assertSame('Organization', $restoredToken->getOrganization()->getName());
    }
}
