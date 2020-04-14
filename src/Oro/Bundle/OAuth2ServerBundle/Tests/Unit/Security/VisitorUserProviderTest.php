<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\VisitorUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class VisitorUserProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $innerUserProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $customerVisitorManager;

    /** @var VisitorUserProvider */
    private $visitorUserProvider;

    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->innerUserProvider = $this->createMock(UserProviderInterface::class);
        $this->customerVisitorManager = $this->createMock('Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager');

        $this->visitorUserProvider = new VisitorUserProvider($this->innerUserProvider, $this->customerVisitorManager);
    }

    public function testRefreshUser()
    {
        $user = $this->createMock(UserInterface::class);

        $this->innerUserProvider->expects($this->once())
            ->method('refreshUser')
            ->with($user)
            ->willReturn($user);

        $this->assertEquals($user, $this->visitorUserProvider->refreshUser($user));
    }

    public function testSupportsClass()
    {
        $class = \stdClass::class;
        $this->innerUserProvider->expects($this->once())
            ->method('supportsClass')
            ->with($class)
            ->willReturn(true);

        $this->assertTrue($this->visitorUserProvider->supportsClass($class));
    }

    public function testLoadUserByUsernameOnNonVisitorIdentifier()
    {
        $identifier = 'testId';
        $user = $this->createMock(UserInterface::class);

        $this->innerUserProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with($identifier)
            ->willReturn($user);

        $this->assertEquals($user, $this->visitorUserProvider->loadUserByUsername($identifier));
    }

    public function testLoadUserByUsernameOnVisitorIdentifier()
    {
        $identifier = 'visitor:345:test';
        $visitor = $this->createMock('Oro\Bundle\CustomerBundle\Entity\CustomerVisitor');

        $this->innerUserProvider->expects($this->never())
            ->method('loadUserByUsername');
        $this->customerVisitorManager->expects($this->once())
            ->method('find')
            ->with(345, 'test')
            ->willReturn($visitor);

        $this->assertEquals($visitor, $this->visitorUserProvider->loadUserByUsername($identifier));
    }
}
