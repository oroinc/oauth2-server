<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League;

use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use PHPUnit\Framework\TestCase;

class AuthCodeGrantUserIdentifierUtilTest extends TestCase
{
    /**
     * @dataProvider encodeIdentifierDataProvider
     */
    public function testEncodeIdentifier(
        string $userIdentifier,
        ?string $visitorSessionId,
        string $expectedIdentifier
    ): void {
        self::assertEquals(
            $expectedIdentifier,
            AuthCodeGrantUserIdentifierUtil::encodeIdentifier($userIdentifier, $visitorSessionId)
        );
    }

    public static function encodeIdentifierDataProvider(): array
    {
        return [
            ['user_identifier', 'visitor_session_id', 'user_identifier|visitor:visitor_session_id'],
            ['user_identifier', null, 'user_identifier']
        ];
    }
    /**
     * @dataProvider decodeIdentifierDataProvider
     */
    public function testDecodeIdentifier(
        string $identifier,
        string $expectedUserIdentifier,
        ?string $expectedVisitorSessionId
    ): void {
        [$userIdentifier, $visitorSessionId] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($identifier);
        self::assertSame($expectedUserIdentifier, $userIdentifier);
        self::assertSame($expectedVisitorSessionId, $visitorSessionId);
    }

    public static function decodeIdentifierDataProvider(): array
    {
        return [
            ['user_identifier|visitor:visitor_session_id', 'user_identifier', 'visitor_session_id'],
            ['user_identifier', 'user_identifier', null]
        ];
    }
}
