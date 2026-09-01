<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferModeProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SessionTransferModeProviderTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;

    private SessionTransferModeProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->provider = new SessionTransferModeProvider($this->tokenStorage);
    }

    public function testIsInSessionTransferModeWhenSessionTransferToken(): void
    {
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($this->createMock(SessionTransferAuthenticationToken::class));

        self::assertTrue($this->provider->isInSessionTransferMode());
    }

    public function testIsInSessionTransferModeWhenCustomerVisitorSessionTransferToken(): void
    {
        if (!\class_exists('Oro\\Bundle\\CustomerBundle\\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($this->createMock(SessionTransferCustomerVisitorAuthenticationToken::class));

        self::assertTrue($this->provider->isInSessionTransferMode());
    }

    public function testIsInSessionTransferModeWhenOtherToken(): void
    {
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($this->createMock(TokenInterface::class));

        self::assertFalse($this->provider->isInSessionTransferMode());
    }

    public function testIsInSessionTransferModeWhenNoToken(): void
    {
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn(null);

        self::assertFalse($this->provider->isInSessionTransferMode());
    }
}
