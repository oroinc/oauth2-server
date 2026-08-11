<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Model;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\IssuedSessionTransferToken;
use PHPUnit\Framework\TestCase;

class IssuedSessionTransferTokenTest extends TestCase
{
    public function testGetters(): void
    {
        $entity = new SessionTransferToken();
        $token = new IssuedSessionTransferToken('stt_token', 60, $entity);

        self::assertEquals('stt_token', $token->getToken());
        self::assertEquals(60, $token->getExpiresIn());
        self::assertEquals($entity, $token->getEntity());
    }

    /**
     * @dataProvider invalidArgumentsProvider
     */
    public function testInvalidArguments(string $token, int $expiresIn, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new IssuedSessionTransferToken($token, $expiresIn, new SessionTransferToken());
    }

    public function invalidArgumentsProvider(): array
    {
        return [
            'empty token' => ['', 60, 'must not be empty'],
            'zero lifetime' => ['stt_token', 0, 'greater than zero'],
            'negative lifetime' => ['stt_token', -1, 'greater than zero'],
        ];
    }
}
