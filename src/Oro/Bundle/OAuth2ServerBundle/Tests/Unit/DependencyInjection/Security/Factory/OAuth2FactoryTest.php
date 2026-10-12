<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\DependencyInjection\Security\Factory;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory\OAuth2Factory;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\FrontendOAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class OAuth2FactoryTest extends TestCase
{
    private OAuth2Factory $factory;
    private ContainerBuilder $container;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new OAuth2Factory();
        $this->container = new ContainerBuilder();
    }

    public function testCreateAuthenticatorForBackendFirewall(): void
    {
        $authenticatorId = $this->factory->createAuthenticator(
            $this->container,
            'api_secured',
            ['anonymous_customer_user' => false, 'authorization_cookies' => ['test_cookie']],
            'user_provider'
        );

        self::assertSame('oro_oauth2_server.security.authenticator.api_secured', $authenticatorId);
        $authenticator = $this->container->getDefinition($authenticatorId);
        self::assertSame(OAuth2Authenticator::class, $authenticator->getClass());
        self::assertEquals(new Reference('user_provider'), $authenticator->getArgument(3));
        self::assertNull($authenticator->getArgument(8));
        self::assertEquals(
            [['setAuthorizationCookies', [['test_cookie']]]],
            $authenticator->getMethodCalls()
        );
        self::assertFalse(
            $this->container->hasDefinition('oro_oauth2_server.security.visitor_user_provider.api_secured')
        );
    }

    public function testCreateAuthenticatorForFrontendFirewall(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $authenticatorId = $this->factory->createAuthenticator(
            $this->container,
            'frontend_api_secured',
            ['anonymous_customer_user' => true, 'authorization_cookies' => ['test_cookie']],
            'user_provider'
        );

        self::assertSame('oro_oauth2_server.security.authenticator.frontend_api_secured', $authenticatorId);
        $authenticator = $this->container->getDefinition($authenticatorId);
        self::assertSame(FrontendOAuth2Authenticator::class, $authenticator->getClass());
        self::assertEquals(
            new Reference('oro_oauth2_server.security.visitor_user_provider.frontend_api_secured'),
            $authenticator->getArgument(3)
        );
        self::assertEquals(
            new Reference('oro_customer.authentication.anonymous_customer_user_roles_provider'),
            $authenticator->getArgument(8)
        );
        self::assertEquals(
            [
                ['setAuthorizationCookies', [['test_cookie']]],
                [
                    'setClientOwnerScopeValidator',
                    [new Reference('oro_oauth2_server.security.client_owner_scope_validator')]
                ]
            ],
            $authenticator->getMethodCalls()
        );

        $visitorUserProvider = $this->container->getDefinition(
            'oro_oauth2_server.security.visitor_user_provider.frontend_api_secured'
        );
        self::assertSame(VisitorUserProvider::class, $visitorUserProvider->getClass());
        self::assertEquals(new Reference('user_provider'), $visitorUserProvider->getArgument(0));
        self::assertEquals(
            new Reference('oro_customer.customer_visitor_manager'),
            $visitorUserProvider->getArgument(1)
        );
    }
}
