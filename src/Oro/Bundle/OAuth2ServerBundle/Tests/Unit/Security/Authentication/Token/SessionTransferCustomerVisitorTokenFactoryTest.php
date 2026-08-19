<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Token;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserTokenFactoryInterface;
use Oro\Bundle\CustomerBundle\Security\Token\ApiAnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorTokenFactory;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SessionTransferCustomerVisitorTokenFactoryTest extends TestCase
{
    private AnonymousCustomerUserTokenFactoryInterface&MockObject $innerFactory;
    private RequestStack $requestStack;
    private SessionTransferCustomerVisitorTokenFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        if (!\class_exists('Oro\\Bundle\\CustomerBundle\\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->innerFactory = $this->createMock(AnonymousCustomerUserTokenFactoryInterface::class);
        $this->requestStack = new RequestStack();
        $this->factory = new SessionTransferCustomerVisitorTokenFactory(
            $this->innerFactory,
            $this->requestStack
        );
    }

    public function testCreateSessionTransferCustomerVisitorToken(): void
    {
        $visitor = (new CustomerVisitor())->setSessionId('visitor-session-id');
        $organization = new Organization();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set(
            SessionTransferCustomerVisitorTokenFactory::SESSION_KEY,
            'visitor-session-id'
        );
        $this->requestStack->push($request);
        $this->innerFactory->expects(self::never())->method('create');

        $token = $this->factory->create($visitor, $organization, ['ROLE_FRONTEND_ANONYMOUS']);

        self::assertInstanceOf(SessionTransferCustomerVisitorAuthenticationToken::class, $token);
        self::assertSame($visitor, $token->getVisitor());
        self::assertSame($organization, $token->getOrganization());
        self::assertSame(['ROLE_FRONTEND_ANONYMOUS'], $token->getRoleNames());
    }

    public function testCreateStandardCustomerVisitorTokenWhenSessionVisitorDoesNotMatch(): void
    {
        $visitor = (new CustomerVisitor())->setSessionId('visitor-session-id');
        $organization = new Organization();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set(
            SessionTransferCustomerVisitorTokenFactory::SESSION_KEY,
            'another-visitor-session-id'
        );
        $this->requestStack->push($request);
        $expectedToken = new AnonymousCustomerUserToken($visitor, [], $organization);
        $this->innerFactory->expects(self::once())
            ->method('create')
            ->with($visitor, $organization, [])
            ->willReturn($expectedToken);

        self::assertSame($expectedToken, $this->factory->create($visitor, $organization));
    }

    public function testCreateApiDelegatesToInnerFactory(): void
    {
        $visitor = (new CustomerVisitor())->setSessionId('visitor-session-id');
        $organization = new Organization();
        $expectedToken = new ApiAnonymousCustomerUserToken($visitor, [], $organization);
        $this->innerFactory->expects(self::once())
            ->method('createApi')
            ->with($visitor, $organization, [])
            ->willReturn($expectedToken);

        self::assertSame($expectedToken, $this->factory->createApi($visitor, $organization));
    }
}
