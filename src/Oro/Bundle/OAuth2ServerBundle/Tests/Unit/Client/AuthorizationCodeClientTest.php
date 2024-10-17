<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Client;

use Oro\Bundle\OAuth2ServerBundle\Client\AuthorizationCodeClient;
use Oro\Bundle\OAuth2ServerBundle\Controller\AuthorizeClientController;
use Oro\Bundle\OAuth2ServerBundle\Generator\OAuth2CodeGenerator;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ClientRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AuthorizationCodeClientTest extends TestCase
{
    private HttpKernelInterface|MockObject $httpKernel;

    private ClientRepository|MockObject $clientRepository;

    private RequestStack|MockObject $requestStack;

    private AuthorizationCodeClient $client;

    protected function setUp(): void
    {
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
        $this->clientRepository = $this->createMock(ClientRepository::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->client = new AuthorizationCodeClient($this->httpKernel, $this->clientRepository, $this->requestStack);
    }

    public function testGetAuthCodeReturnsNullWhenClientNotFound(): void
    {
        $clientIdentifier = 'invalid_client_id';

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn(null);

        $result = $this->client->getAuthCode($clientIdentifier);

        self::assertNull($result);
    }

    public function testGetAuthCodeReturnsNullWhenLocationHeaderMissing(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);
        $requestBody = [
            'response_type' => 'code',
            'client_id' => $clientIdentifier,
            'code_challenge_method' => OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
        ];

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(false);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $response = new Response();
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($requestBody) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertContains($requestBody, $request->request->all());
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'backoffice',
                    ],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getAuthCode($clientIdentifier);

        self::assertNull($result);
    }

    public function testGetAuthCodeReturnsNullWhenNoCodeInResponse(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(false);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $response = new Response('', Response::HTTP_OK, ['Location' => 'http://example.com/']);
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($clientIdentifier) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertContains(
                    [
                        'response_type' => 'code',
                        'client_id' => $clientIdentifier,
                        'code_challenge_method' => OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
                    ],
                    $request->request->all()
                );
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'backoffice',
                    ],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getAuthCode($clientIdentifier);

        self::assertNull($result);
    }

    public function testGetAuthCodeReturnsCodeAndVerifier(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(false);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $authCode = 'auth_code_value';
        $response = new Response('', Response::HTTP_OK, ['Location' => 'http://example.com/?code=' . $authCode]);
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($clientIdentifier, $response) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_GET, $request->getMethod());
                self::assertEquals('code', $request->query->get('response_type'));
                self::assertEquals($clientIdentifier, $request->query->get('client_id'));
                self::assertEquals(
                    OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
                    $request->query->get('code_challenge_method')
                );
                self::assertTrue($request->query->has('code_challenge'));
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'backoffice',
                    ],
                    $request->attributes->all()
                );

                return $response;
            });

        $result = $this->client->getAuthCode($clientIdentifier);

        self::assertIsArray($result);
        self::assertArrayHasKey('code', $result);
        self::assertArrayHasKey('code_verifier', $result);
        self::assertEquals($authCode, $result['code']);
        self::assertNotEmpty($result['code_verifier']);
    }

    public function testGetAuthCodeReturnsCodeAndVerifierWhenCurrentRequestMethodIsPost(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(false);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/', 'POST'));

        $authCode = 'auth_code_value';
        $response = new Response('', Response::HTTP_OK, ['Location' => 'http://example.com/?code=' . $authCode]);
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($clientIdentifier, $response) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertEquals('true', $request->request->get('grantAccess'));
                self::assertEquals('code', $request->query->get('response_type'));
                self::assertEquals($clientIdentifier, $request->query->get('client_id'));
                self::assertEquals(
                    OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
                    $request->query->get('code_challenge_method')
                );
                self::assertTrue($request->query->has('code_challenge'));
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'backoffice',
                    ],
                    $request->attributes->all()
                );

                return $response;
            });

        $result = $this->client->getAuthCode($clientIdentifier);

        self::assertIsArray($result);
        self::assertArrayHasKey('code', $result);
        self::assertArrayHasKey('code_verifier', $result);
        self::assertEquals($authCode, $result['code']);
        self::assertNotEmpty($result['code_verifier']);
    }

    public function testGetAuthCodeReturnsCodeAndVerifierWhenNoCurrentRequest(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(false);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $authCode = 'auth_code_value';
        $response = new Response('', Response::HTTP_OK, ['Location' => 'http://example.com/?code=' . $authCode]);
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($clientIdentifier) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertContains(
                    [
                        'response_type' => 'code',
                        'client_id' => $clientIdentifier,
                        'code_challenge_method' => OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
                    ],
                    $request->request->all()
                );
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'backoffice',
                    ],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getAuthCode($clientIdentifier);
        self::assertIsArray($result);
        self::assertArrayHasKey('code', $result);
        self::assertArrayHasKey('code_verifier', $result);
        self::assertEquals('auth_code_value', $result['code']);
        self::assertNotEmpty($result['code_verifier']);
    }

    public function testGetAuthCodeReturnsCodeAndVerifierWhenFrontendClient(): void
    {
        $clientIdentifier = 'client_id';
        $clientEntity = $this->createMock(ClientEntity::class);

        $this->clientRepository
            ->expects(self::once())
            ->method('getClientEntity')
            ->with($clientIdentifier)
            ->willReturn($clientEntity);

        $clientEntity
            ->expects(self::once())
            ->method('isFrontend')
            ->willReturn(true);

        $this->requestStack
            ->expects(self::once())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'));

        $authCode = 'auth_code_value';
        $response = new Response('', Response::HTTP_OK, ['Location' => 'http://example.com/?code=' . $authCode]);
        $this->httpKernel
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function ($request, $type) use ($clientIdentifier) {
                self::assertEquals(HttpKernelInterface::SUB_REQUEST, $type);
                self::assertEquals(Request::METHOD_POST, $request->getMethod());
                self::assertContains(
                    [
                        'response_type' => 'code',
                        'client_id' => $clientIdentifier,
                        'code_challenge_method' => OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
                    ],
                    $request->request->all()
                );
                self::assertEquals(
                    [
                        '_controller' => AuthorizeClientController::class . '::authorizeAction',
                        'type' => 'frontend',
                    ],
                    $request->attributes->all()
                );
            })
            ->willReturn($response);

        $result = $this->client->getAuthCode($clientIdentifier);
        self::assertIsArray($result);
        self::assertArrayHasKey('code', $result);
        self::assertArrayHasKey('code_verifier', $result);
        self::assertEquals('auth_code_value', $result['code']);
        self::assertNotEmpty($result['code_verifier']);
    }
}
