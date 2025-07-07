<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class VisitorAccessTokenParserTest extends TestCase
{
    private AuthorizationValidatorInterface&MockObject $authorizationValidator;
    private ServerRequestFactoryInterface&MockObject $serverRequestFactory;
    private LoggerInterface&MockObject $logger;
    private VisitorAccessTokenParser $parser;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->authorizationValidator = $this->createMock(AuthorizationValidatorInterface::class);
        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->parser = new VisitorAccessTokenParser(
            $this->authorizationValidator,
            $this->serverRequestFactory,
            $this->logger
        );
    }

    private function expectCreateRequest(string $visitorAccessToken): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $this->serverRequestFactory->expects(self::once())
            ->method('createServerRequest')
            ->with('GET', '/')
            ->willReturn($request);
        $request->expects(self::once())
            ->method('withHeader')
            ->with('authorization', 'Bearer ' . $visitorAccessToken)
            ->willReturnSelf();

        return $request;
    }

    public function testGetVisitorSessionId(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $visitorAccessToken = 'test_access_token';
        $visitorSessionId = 'test_visitor';
        $validatedRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->expectCreateRequest($visitorAccessToken);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->with(self::identicalTo($request))
            ->willReturn($validatedRequest);

        $validatedRequest->expects(self::once())
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn(VisitorIdentifierUtil::encodeIdentifier($visitorSessionId));

        $this->logger->expects(self::never())
            ->method(self::anything());

        self::assertEquals($visitorSessionId, $this->parser->getVisitorSessionId($visitorAccessToken));
    }

    public function testGetVisitorSessionIdForInvalidVisitorAccessToken(): void
    {
        $visitorAccessToken = 'test_access_token';
        $exception = new \Exception('some error');

        $request = $this->expectCreateRequest($visitorAccessToken);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->with(self::identicalTo($request))
            ->willThrowException($exception);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'The visitor access token is invalid.',
                ['visitor_access_token' => $visitorAccessToken, 'exception' => $exception]
            );

        self::assertNull($this->parser->getVisitorSessionId($visitorAccessToken));
    }

    public function testGetVisitorSessionIdForVisitorAccessTokenWithoutUserIdentifier(): void
    {
        $visitorAccessToken = 'test_access_token';
        $validatedRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->expectCreateRequest($visitorAccessToken);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->with(self::identicalTo($request))
            ->willReturn($validatedRequest);

        $validatedRequest->expects(self::once())
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'The visitor access token does not contains a visitor identifier.',
                ['visitor_access_token' => $visitorAccessToken]
            );

        self::assertNull($this->parser->getVisitorSessionId($visitorAccessToken));
    }

    public function testGetVisitorSessionIdForVisitorAccessTokenWithInvalidUserIdentifier(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $visitorAccessToken = 'test_access_token';
        $validatedRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->expectCreateRequest($visitorAccessToken);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->with(self::identicalTo($request))
            ->willReturn($validatedRequest);

        $validatedRequest->expects(self::once())
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn('test_user');

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'The visitor access token does not contains a valid visitor identifier.',
                ['visitor_access_token' => $visitorAccessToken]
            );

        self::assertNull($this->parser->getVisitorSessionId($visitorAccessToken));
    }
}
