<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\BackendSessionTransferRouteContextValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class BackendSessionTransferRouteContextValidatorTest extends TestCase
{
    public function testSupportsOnlyBackendClient(): void
    {
        $validator = new BackendSessionTransferRouteContextValidator($this->createMock(RouterInterface::class));
        $client = new Client();

        self::assertTrue($validator->supports($client));

        $client->setFrontend(true);
        self::assertFalse($validator->supports($client));
    }

    public function testValidate(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('target_route', new Route('/target/{id}', methods: ['GET']));
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('getRouteCollection')->willReturn($routeCollection);
        $router->expects(self::once())
            ->method('generate')
            ->with('target_route', ['id' => 10], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/target/10');

        $target = (new BackendSessionTransferRouteContextValidator($router))
            ->validate('target_route', ['id' => 10], new Client());

        self::assertEquals('target_route', $target->getRoute());
        self::assertEquals(['id' => 10], $target->getRouteParameters());
        self::assertEquals([], $target->getContextData());
    }

    public function testValidateAllowsLongRouteAndManyParameters(): void
    {
        $routeName = \str_repeat('r', 256);
        $routeParameters = [];
        for ($i = 0; $i < 51; $i++) {
            $routeParameters['parameter_' . $i] = 'value';
        }
        $routeParameters['parameter_0'] = \str_repeat('v', 2049);
        $routeCollection = new RouteCollection();
        $routeCollection->add($routeName, new Route('/target'));
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('getRouteCollection')->willReturn($routeCollection);
        $router->expects(self::once())
            ->method('generate')
            ->with($routeName, $routeParameters, UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/target');

        $target = (new BackendSessionTransferRouteContextValidator($router))
            ->validate($routeName, $routeParameters, new Client());

        self::assertSame($routeName, $target->getRoute());
        self::assertSame($routeParameters, $target->getRouteParameters());
    }

    public function testValidateRejectsUnknownRoute(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('getRouteCollection')
            ->willReturn(new RouteCollection());
        $validator = new BackendSessionTransferRouteContextValidator($router);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('unknown_route', [], new Client());
    }

    public function testValidateRejectsFrontendRoute(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('frontend_route', new Route('/frontend', options: ['frontend' => true]));
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('getRouteCollection')->willReturn($routeCollection);
        $validator = new BackendSessionTransferRouteContextValidator($router);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('frontend_route', [], new Client());
    }

    public function testValidateRejectsRouteWithoutGetMethod(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('post_route', new Route('/post', methods: ['POST']));
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);
        $validator = new BackendSessionTransferRouteContextValidator($router);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('post_route', [], new Client());
    }

    public function testValidateRejectsInternalRouteParameter(): void
    {
        $validator = new BackendSessionTransferRouteContextValidator($this->createMock(RouterInterface::class));

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('target_route', ['_controller' => 'service'], new Client());
    }

    public function testValidateRejectsForbiddenRoute(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('oro_oauth2_session_transfer_consume', new Route('/oauth2/session-transfer/consume'));
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);
        $validator = new BackendSessionTransferRouteContextValidator($router);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('oro_oauth2_session_transfer_consume', [], new Client());
    }
}
