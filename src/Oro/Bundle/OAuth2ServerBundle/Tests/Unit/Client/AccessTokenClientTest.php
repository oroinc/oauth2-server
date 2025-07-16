<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Client;

use Oro\Bundle\OAuth2ServerBundle\Client\AccessTokenClient;
use Oro\Bundle\OAuth2ServerBundle\Controller\AuthorizationTokenController;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Server\AuthorizationServer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AccessTokenClientTest extends TestCase
{
    private HttpKernelInterface&MockObject $httpKernel;
    private AuthorizationServer&MockObject $authorizationServer;
    private RequestStack&MockObject $requestStack;
    private AccessTokenClient $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->client = new AccessTokenClient($this->httpKernel, $this->authorizationServer, $this->requestStack);
    }

    public function testGetTokenByAuthorizationCode(): void
    {
        $clientIdentifier = 'client_id';
        $authCode = 'auth_code';
        $codeVerifier = 'code_verifier';
        $requestBody = [
            'grant_type' => Client::AUTHORIZATION_CODE,
            'client_id' => $clientIdentifier,
            'code' => $authCode,
            'code_verifier' => $codeVerifier
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $request = Request::create('/');
        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $attributes = ['_controller' => AuthorizationTokenController::class . '::tokenAction'];
        $subRequest = $request->duplicate([], $requestBody, $attributes);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->willReturn($response);

        $result = $this->client->getTokenByAuthorizationCode($clientIdentifier, $authCode, $codeVerifier);

        self::assertEquals($expectedResponseContent, $result);
    }

    public function testGetTokenByAuthorizationCodeWithTtl(): void
    {
        $clientIdentifier = 'client_id';
        $authCode = 'auth_code';
        $codeVerifier = 'code_verifier';
        $ttlDateInterval = \DateInterval::createFromDateString('1 day');
        $requestBody = [
            'grant_type' => Client::AUTHORIZATION_CODE,
            'client_id' => $clientIdentifier,
            'code' => $authCode,
            'code_verifier' => $codeVerifier
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $request = Request::create('/');
        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $attributes = ['_controller' => AuthorizationTokenController::class . '::tokenAction'];
        $subRequest = $request->duplicate([], $requestBody, $attributes);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->willReturn($response);

        $originalTtlDateInterval = \DateInterval::createFromDateString('1 hour');
        $this->authorizationServer->expects(self::once())
            ->method('getGrantTypeAccessTokenTTL')
            ->with(Client::AUTHORIZATION_CODE)
            ->willReturn($originalTtlDateInterval);

        $this->authorizationServer->expects(self::exactly(2))
            ->method('setGrantTypeAccessTokenTTL')
            ->withConsecutive(
                [Client::AUTHORIZATION_CODE, $ttlDateInterval],
                [Client::AUTHORIZATION_CODE, $originalTtlDateInterval]
            );

        $result = $this->client->getTokenByAuthorizationCode(
            $clientIdentifier,
            $authCode,
            $codeVerifier,
            $ttlDateInterval
        );

        self::assertEquals($expectedResponseContent, $result);
    }

    public function testGetTokenByAuthorizationCodeWithoutCurrentRequest(): void
    {
        $clientIdentifier = 'client_id';
        $authCode = 'auth_code';
        $codeVerifier = 'code_verifier';
        $requestBody = [
            'grant_type' => Client::AUTHORIZATION_CODE,
            'client_id' => $clientIdentifier,
            'code' => $authCode,
            'code_verifier' => $codeVerifier
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($requestBody) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertEquals($requestBody, $request->request->all());
                self::assertContains(
                    ['_controller' => AuthorizationTokenController::class . '::tokenAction'],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getTokenByAuthorizationCode($clientIdentifier, $authCode, $codeVerifier);

        self::assertEquals($expectedResponseContent, $result);
    }

    public function testGetTokenByRefreshToken(): void
    {
        $clientIdentifier = 'client_id';
        $refreshToken = 'refresh_token';
        $requestBody = [
            'grant_type' => Client::REFRESH_TOKEN,
            'client_id' => $clientIdentifier,
            'refresh_token' => $refreshToken
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $request = Request::create('/');
        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $attributes = ['_controller' => AuthorizationTokenController::class . '::tokenAction'];
        $subRequest = $request->duplicate([], $requestBody, $attributes);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->willReturn($response);

        $result = $this->client->getTokenByRefreshToken($clientIdentifier, $refreshToken);

        self::assertEquals($expectedResponseContent, $result);
    }

    public function testGetTokenByRefreshTokenWithTtl(): void
    {
        $ttlDateInterval = \DateInterval::createFromDateString('1 day');
        $clientIdentifier = 'client_id';
        $refreshToken = 'refresh_token';
        $requestBody = [
            'grant_type' => Client::REFRESH_TOKEN,
            'client_id' => $clientIdentifier,
            'refresh_token' => $refreshToken
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $request = Request::create('/');
        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $attributes = ['_controller' => AuthorizationTokenController::class . '::tokenAction'];
        $subRequest = $request->duplicate([], $requestBody, $attributes);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->willReturn($response);

        $originalTtlDateInterval = \DateInterval::createFromDateString('1 hour');
        $this->authorizationServer->expects(self::once())
            ->method('getGrantTypeAccessTokenTTL')
            ->with(Client::REFRESH_TOKEN)
            ->willReturn($originalTtlDateInterval);

        $this->authorizationServer->expects(self::exactly(2))
            ->method('setGrantTypeAccessTokenTTL')
            ->withConsecutive(
                [Client::REFRESH_TOKEN, $ttlDateInterval],
                [Client::REFRESH_TOKEN, $originalTtlDateInterval]
            );

        $result = $this->client->getTokenByRefreshToken($clientIdentifier, $refreshToken, $ttlDateInterval);

        self::assertEquals($expectedResponseContent, $result);
    }

    public function testGetTokenByRefreshTokenWithoutCurrentRequest(): void
    {
        $clientIdentifier = 'client_id';
        $refreshToken = 'refresh_token';
        $requestBody = [
            'grant_type' => Client::REFRESH_TOKEN,
            'client_id' => $clientIdentifier,
            'refresh_token' => $refreshToken
        ];
        $expectedResponseContent = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value'
        ];

        $this->requestStack->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $response = new Response(json_encode($expectedResponseContent, JSON_THROW_ON_ERROR));
        $this->httpKernel->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($requestBody) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertEquals($requestBody, $request->request->all());
                self::assertContains(
                    ['_controller' => AuthorizationTokenController::class . '::tokenAction'],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getTokenByRefreshToken($clientIdentifier, $refreshToken);

        self::assertEquals($expectedResponseContent, $result);
    }
}
