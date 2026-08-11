<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\ResponseType;

use GuzzleHttp\Psr7\Response;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\League\ResponseType\SessionTransferTokenResponse;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\IssuedSessionTransferToken;
use PHPUnit\Framework\TestCase;

class SessionTransferTokenResponseTest extends TestCase
{
    public function testGenerateHttpResponse(): void
    {
        $issuedToken = new IssuedSessionTransferToken(
            'stt_token',
            60,
            new SessionTransferToken()
        );
        $responseType = new SessionTransferTokenResponse();
        $responseType->setSessionTransferToken($issuedToken);

        $response = $responseType->generateHttpResponse(new Response());

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertEquals('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertEquals('no-cache', $response->getHeaderLine('Pragma'));
        self::assertEquals(
            [
                'token_type' => 'SessionTransfer',
                'access_token' => 'stt_token',
                'expires_in' => 60,
            ],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testGenerateHttpResponseWithoutToken(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must be set');

        (new SessionTransferTokenResponse())->generateHttpResponse(new Response());
    }
}
