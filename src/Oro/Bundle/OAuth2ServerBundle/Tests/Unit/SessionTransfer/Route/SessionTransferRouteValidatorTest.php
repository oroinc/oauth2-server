<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\SessionTransferRouteContextValidatorInterface;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\SessionTransferRouteValidator;
use PHPUnit\Framework\TestCase;

class SessionTransferRouteValidatorTest extends TestCase
{
    public function testValidateUsesFirstSupportingValidator(): void
    {
        $client = new Client();
        $target = new SessionTransferTarget('target_route', ['id' => 10]);
        $unsupportedValidator = $this->createMock(SessionTransferRouteContextValidatorInterface::class);
        $supportedValidator = $this->createMock(SessionTransferRouteContextValidatorInterface::class);
        $unsupportedValidator->expects(self::once())->method('supports')->with($client)->willReturn(false);
        $unsupportedValidator->expects(self::never())->method('validate');
        $supportedValidator->expects(self::once())->method('supports')->with($client)->willReturn(true);
        $supportedValidator->expects(self::once())
            ->method('validate')
            ->with('target_route', ['id' => 10], $client)
            ->willReturn($target);

        $validator = new SessionTransferRouteValidator([$unsupportedValidator, $supportedValidator]);

        self::assertSame($target, $validator->validate('target_route', ['id' => 10], $client));
    }

    public function testValidateWithoutBackendValidator(): void
    {
        $validator = new SessionTransferRouteValidator([]);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);

        $validator->validate('target_route', [], new Client());
    }

    public function testValidateWithoutFrontendValidator(): void
    {
        $client = new Client();
        $client->setFrontend(true);
        $validator = new SessionTransferRouteValidator([]);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);

        $validator->validate('target_route', [], $client);
    }
}
