<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Api\OAuth2TokenAccessChecker;
use Oro\Bundle\OAuth2ServerBundle\EventListener\OldApiAccessRequestListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class OldApiAccessRequestListenerTest extends TestCase
{
    private OAuth2TokenAccessChecker&MockObject $accessChecker;
    private OldApiAccessRequestListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->accessChecker = $this->createMock(OAuth2TokenAccessChecker::class);

        $this->listener = new OldApiAccessRequestListener('^/api/rest.*', $this->accessChecker);
    }

    private function getRequestEvent(string $requestUrl, bool $isMainRequest = true): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($requestUrl),
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }

    public function testOnKernelRequestForSubRequest(): void
    {
        $this->accessChecker->expects(self::never())
            ->method('isAccessGranted');

        $this->listener->onKernelRequest($this->getRequestEvent('/api/rest', false));
    }

    public function testOnKernelRequestForNotApiRequest(): void
    {
        $this->accessChecker->expects(self::never())
            ->method('isAccessGranted');

        $this->listener->onKernelRequest($this->getRequestEvent('/another'));
    }

    public function testOnKernelRequestForApiRequestAndAccessDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('This API is not accessible via the current OAuth2 token.');

        $this->accessChecker->expects(self::once())
            ->method('isAccessGranted')
            ->with(new RequestType([]))
            ->willReturn(false);

        $this->listener->onKernelRequest($this->getRequestEvent('/api/rest'));
    }

    public function testOnKernelRequestForApiRequestAndAccessGranted(): void
    {
        $this->accessChecker->expects(self::once())
            ->method('isAccessGranted')
            ->with(new RequestType([]))
            ->willReturn(true);

        $this->listener->onKernelRequest($this->getRequestEvent('/api/rest'));
    }
}
