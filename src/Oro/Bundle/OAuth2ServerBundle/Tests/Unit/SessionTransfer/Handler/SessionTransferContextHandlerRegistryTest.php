<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Handler;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler\SessionTransferContextHandlerInterface;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler\SessionTransferContextHandlerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SessionTransferContextHandlerRegistryTest extends TestCase
{
    public function testGetHandler(): void
    {
        $token = (new SessionTransferToken())->setClient(new Client());
        $unsupportedHandler = $this->createMock(SessionTransferContextHandlerInterface::class);
        $supportedHandler = $this->createMock(SessionTransferContextHandlerInterface::class);
        $unsupportedHandler->expects(self::once())->method('supports')->with($token)->willReturn(false);
        $supportedHandler->expects(self::once())->method('supports')->with($token)->willReturn(true);

        $registry = new SessionTransferContextHandlerRegistry([$unsupportedHandler, $supportedHandler]);

        self::assertSame($supportedHandler, $registry->getHandler($token));
    }

    public function testGetHandlerForBackendTokenWithoutMatchingHandler(): void
    {
        $token = (new SessionTransferToken())->setClient(new Client());
        $registry = new SessionTransferContextHandlerRegistry([]);

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Back-office Session Transfer is not available.');

        $registry->getHandler($token);
    }

    public function testGetHandlerForFrontendTokenWithoutMatchingHandler(): void
    {
        $client = new Client();
        $client->setFrontend(true);
        $token = (new SessionTransferToken())->setClient($client);
        $registry = new SessionTransferContextHandlerRegistry([]);

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Storefront Session Transfer is not available.');

        $registry->getHandler($token);
    }
}
