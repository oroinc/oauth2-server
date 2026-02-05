<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Api;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Api\OAuth2TokenAccessChecker;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class OAuth2TokenAccessCheckerTest extends TestCase
{
    private TokenAccessor&MockObject $tokenAccessor;
    private ApiDocViewProvider&MockObject $apiDocViewProvider;
    private OAuth2TokenAccessChecker $accessChecker;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenAccessor = $this->createMock(TokenAccessor::class);
        $this->apiDocViewProvider = $this->createMock(ApiDocViewProvider::class);

        $this->accessChecker = new OAuth2TokenAccessChecker(
            $this->tokenAccessor,
            $this->apiDocViewProvider
        );
    }

    private function getClient(bool $allApis, array $apis = []): Client
    {
        $client = new Client();
        $client->setAllApis($allApis);
        $client->setApis($apis);

        return $client;
    }

    public function testIsAccessGrantedWhenTokenIsNull(): void
    {
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn(null);

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGranted(new RequestType(['rest']))
        );
    }

    public function testIsAccessGrantedWhenTokenIsNotOAuth2Token(): void
    {
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn($this->createMock(TokenInterface::class));

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGranted(new RequestType(['rest']))
        );
    }

    public function testIsAccessGrantedWhenClientAttributeIsNotSet(): void
    {
        $token = $this->createMock(OAuth2Token::class);
        $token->expects(self::once())
            ->method('getAttribute')
            ->with('client')
            ->willReturn(null);
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGranted(new RequestType(['rest']))
        );
    }

    public function testIsAccessGrantedWhenClientAttributeIsNotClientInstance(): void
    {
        $token = $this->createMock(OAuth2Token::class);
        $token->expects(self::once())
            ->method('getAttribute')
            ->with('client')
            ->willReturn(new \stdClass());
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGranted(new RequestType(['rest']))
        );
    }

    public function testIsAccessGrantedWhenClientHasAllApisEnabled(): void
    {
        $client = $this->getClient(true);

        $token = $this->createMock(OAuth2Token::class);
        $token->expects(self::once())
            ->method('getAttribute')
            ->with('client')
            ->willReturn($client);
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGranted(new RequestType(['rest']))
        );
    }

    public function testIsAccessGrantedByClientWhenAllApisEnabled(): void
    {
        $client = $this->getClient(true);

        $this->apiDocViewProvider->expects(self::never())
            ->method('getViewsByRequestType');

        self::assertTrue(
            $this->accessChecker->isAccessGrantedByClient(new RequestType(['rest']), $client)
        );
    }

    /**
     * @dataProvider isAccessGrantedDataProvider
     */
    public function testIsAccessGranted(array $views, bool $result): void
    {
        $requestType = new RequestType(['rest', 'frontend']);
        $client = $this->getClient(false, ['frontend_api', 'frontend']);

        $token = $this->createMock(OAuth2Token::class);
        $token->expects(self::once())
            ->method('getAttribute')
            ->with('client')
            ->willReturn($client);
        $this->tokenAccessor->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $this->apiDocViewProvider->expects(self::once())
            ->method('getViewsByRequestType')
            ->with(self::identicalTo($requestType))
            ->willReturn($views);

        self::assertSame(
            $result,
            $this->accessChecker->isAccessGranted($requestType)
        );
    }

    /**
     * @dataProvider isAccessGrantedDataProvider
     */
    public function testIsAccessGrantedByClient(array $views, bool $result): void
    {
        $requestType = new RequestType(['rest', 'frontend']);
        $client = $this->getClient(false, ['frontend_api', 'frontend']);

        $this->apiDocViewProvider->expects(self::once())
            ->method('getViewsByRequestType')
            ->with(self::identicalTo($requestType))
            ->willReturn($views);

        self::assertSame(
            $result,
            $this->accessChecker->isAccessGrantedByClient($requestType, $client)
        );
    }

    public static function isAccessGrantedDataProvider(): array
    {
        return [
            'views not found' => [
                'views' => [],
                'result' => false
            ],
            'client APIs match found views' => [
                'views' => ['other' => [], 'frontend' => []],
                'result' => true
            ],
            'client APIs do not match found views' => [
                'views' => ['other' => []],
                'result' => false
            ]
        ];
    }
}
