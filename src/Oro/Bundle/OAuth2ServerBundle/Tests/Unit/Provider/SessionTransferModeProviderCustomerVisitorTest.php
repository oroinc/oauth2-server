<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferModeProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Covers the Session Transfer token of a customer visitor, which exists only when CustomerBundle is installed.
 */
final class SessionTransferModeProviderCustomerVisitorTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;

    private SessionTransferModeProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Can be tested only with CustomerBundle.');
        }

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->provider = new SessionTransferModeProvider($this->tokenStorage);
    }

    public function testIsInSessionTransferModeWhenCustomerVisitorSessionTransferToken(): void
    {
        $this->tokenStorage
            ->expects(self::once())
            ->method('getToken')
            ->willReturn($this->createMock(SessionTransferCustomerVisitorAuthenticationToken::class));

        self::assertTrue($this->provider->isInSessionTransferMode());
    }
}
