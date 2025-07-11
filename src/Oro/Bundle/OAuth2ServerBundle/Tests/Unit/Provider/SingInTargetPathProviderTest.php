<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\CustomerBundle\Layout\DataProvider\SignInTargetPathProviderInterface;
use Oro\Bundle\OAuth2ServerBundle\Provider\SingInTargetPathProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SingInTargetPathProviderTest extends TestCase
{
    private SignInTargetPathProviderInterface|MockObject $innerProvider;
    private RequestStack $requestStack;

    private SingInTargetPathProvider $provider;

    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->innerProvider = $this->createMock(SignInTargetPathProviderInterface::class);
        $this->requestStack = new RequestStack();

        $this->provider = new SingInTargetPathProvider($this->innerProvider, $this->requestStack);
    }

    public function testGetTargetPathWithoutRequest(): void
    {
        $this->innerProvider->expects(self::once())
            ->method('getTargetPath')
            ->willReturn('inner_path');

        self::assertEquals('inner_path', $this->provider->getTargetPath());
    }

    public function testGetTargetPathWithNonOauthRequest(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $this->innerProvider->expects(self::once())
            ->method('getTargetPath')
            ->willReturn('inner_path');

        self::assertEquals('inner_path', $this->provider->getTargetPath());
    }

    public function testGetTargetPathWithOauthRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_oauth_login', true);
        $this->requestStack->push($request);

        $this->innerProvider->expects(self::never())
            ->method('getTargetPath');

        self::assertNull($this->provider->getTargetPath());
    }
}
