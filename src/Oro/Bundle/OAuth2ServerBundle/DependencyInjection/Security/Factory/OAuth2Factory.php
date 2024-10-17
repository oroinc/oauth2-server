<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory;

use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorUserProvider;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The factory to configure OAuth 2.0 authentication listener.
 */
class OAuth2Factory implements AuthenticatorFactoryInterface
{
    private const AUTHENTICATOR_SERVICE  = 'oro_oauth2_server.security.authenticator';

    #[\Override]
    public function createAuthenticator(
        ContainerBuilder $container,
        string $firewallName,
        array $config,
        string $userProviderId
    ): string {
        $authenticatorId = self::AUTHENTICATOR_SERVICE . '.' . $firewallName;

        $container
            ->register($authenticatorId, OAuth2Authenticator::class)
            ->addArgument(new Reference('logger'))
            ->addArgument(new Reference('oro_oauth2_server.client_manager'))
            ->addArgument(new Reference('doctrine'))
            ->addArgument(new Reference($this->getUserProvider($container, $firewallName, $config, $userProviderId)))
            ->addArgument(new Reference('oro_oauth2_server.league.authorization_validator'))
            ->addArgument(new Reference('Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface'))
            ->addArgument(new Reference('oro_api.security.authenticator.feature_checker'))
            ->addArgument($firewallName)
            ->addArgument(
                $this->isVisitorFirewall($config) ? $this->createAnonymousCustomerUserRolesProvider() : null
            )
            ->addMethodCall('setAuthorizationCookies', [$config['authorization_cookies']]);
        ;

        return $authenticatorId;
    }

    #[\Override]
    public function getPriority(): int
    {
        return -10;
    }

    #[\Override]
    public function getKey(): string
    {
        return 'oauth2';
    }

    #[\Override]
    public function addConfiguration(NodeDefinition $builder): void
    {
        $builder
            ->children()
                ->booleanNode('anonymous_customer_user')->defaultValue(false)->end()
                ->arrayNode('authorization_cookies')->defaultValue([])->scalarPrototype()->end()
            ->end();
    }

    /**
     * If the firewall should support visitors, decorates the given user provider and returns it's name.
     * Otherwise, returns the given user provider.
     */
    private function getUserProvider(
        ContainerBuilder $container,
        string $id,
        array $config,
        string $userProvider
    ): string {
        if (!$this->isVisitorFirewall($config)) {
            return $userProvider;
        }

        $definition = new Definition(
            VisitorUserProvider::class,
            [new Reference($userProvider), new Reference('oro_customer.customer_visitor_manager')]
        );
        $definition->setPublic(false);

        $userProviderId = 'oro_oauth2_server.security.visitor_user_provider.' . $id;
        $container->setDefinition($userProviderId, $definition);

        return $userProviderId;
    }

    private function isVisitorFirewall(array $config): bool
    {
        return
            class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')
            && $config['anonymous_customer_user'] === true;
    }

    private function createAnonymousCustomerUserRolesProvider(): Reference
    {
        return new Reference('oro_customer.authentication.anonymous_customer_user_roles_provider');
    }
}
