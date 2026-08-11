<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\FrontendSessionTransferRouteContextValidator;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class FrontendSessionTransferRouteContextValidatorTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!\class_exists('Oro\\Bundle\\WebsiteBundle\\OroWebsiteBundle')) {
            self::markTestSkipped('can be tested only with WebsiteBundle');
        }
    }

    public function testValidateAddsWebsiteContext(): void
    {
        $organization = new Organization();
        ReflectionUtil::setId($organization, 10);
        $website = (new Website())->setOrganization($organization);
        ReflectionUtil::setId($website, 20);
        $client = (new Client())->setOrganization($organization);
        $routeCollection = new RouteCollection();
        $routeCollection->add('frontend_route', new Route('/frontend', options: ['frontend' => true]));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);
        $router->method('generate')->willReturn('/frontend');
        $websiteManager = $this->createMock(WebsiteManager::class);
        $websiteManager->expects(self::once())->method('getCurrentWebsite')->willReturn($website);

        $target = (new FrontendSessionTransferRouteContextValidator($router, $websiteManager))
            ->validate('frontend_route', [], $client);

        self::assertEquals(['website_id' => 20], $target->getContextData());
    }

    public function testValidateRejectsBackendRoute(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('backend_route', new Route('/backend'));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);
        $validator = new FrontendSessionTransferRouteContextValidator(
            $router,
            $this->createMock(WebsiteManager::class)
        );

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(3);

        $validator->validate('backend_route', [], new Client());
    }

    public function testValidateRejectsMissingWebsite(): void
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('frontend_route', new Route('/frontend', options: ['frontend' => true]));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);
        $router->method('generate')->willReturn('/frontend');
        $websiteManager = $this->createMock(WebsiteManager::class);
        $websiteManager->expects(self::once())->method('getCurrentWebsite')->willReturn(null);
        $validator = new FrontendSessionTransferRouteContextValidator($router, $websiteManager);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);

        $validator->validate('frontend_route', [], new Client());
    }
}
