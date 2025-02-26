<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class VisitorUserProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $innerUserProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $visitorManager;

    /** @var VisitorUserProvider */
    private $visitorUserProvider;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->innerUserProvider = $this->createMock(UserProviderInterface::class);
        $this->visitorManager = $this->createMock(CustomerVisitorManager::class);

        $this->visitorUserProvider = new VisitorUserProvider($this->innerUserProvider, $this->visitorManager);
    }

    public function testRefreshUser(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->innerUserProvider->expects(self::once())
            ->method('refreshUser')
            ->with($user)
            ->willReturn($user);

        self::assertEquals($user, $this->visitorUserProvider->refreshUser($user));
    }

    public function testSupportsClass(): void
    {
        $class = \stdClass::class;
        $this->innerUserProvider->expects(self::once())
            ->method('supportsClass')
            ->with($class)
            ->willReturn(true);

        self::assertTrue($this->visitorUserProvider->supportsClass($class));
    }

    public function testLoadUserByUsernameOnNonVisitorIdentifier(): void
    {
        $identifier = 'testId';
        $user = $this->createMock(UserInterface::class);

        $this->innerUserProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with($identifier)
            ->willReturn($user);

        self::assertEquals($user, $this->visitorUserProvider->loadUserByIdentifier($identifier));
    }

    public function testLoadUserByUsernameOnVisitorIdentifier(): void
    {
        $visitor = $this->createMock('Oro\Bundle\CustomerBundle\Entity\CustomerVisitor');

        $this->innerUserProvider->expects(self::never())
            ->method('loadUserByIdentifier');
        $this->visitorManager->expects(self::once())
            ->method('findOrCreate')
            ->with('test')
            ->willReturn($visitor);

        self::assertEquals($visitor, $this->visitorUserProvider->loadUserByIdentifier('visitor:test'));
    }
}
