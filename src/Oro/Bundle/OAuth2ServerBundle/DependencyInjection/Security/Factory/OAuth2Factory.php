<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Provider\VisitorOAuth2Provider;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorUserProvider;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The factory to configure OAuth 2.0 authentication listener.
 */
class OAuth2Factory implements SecurityFactoryInterface
{
    private const FIREWALL_LISTENER_SERVICE        = 'oro_oauth2_server.security.firewall_listener';
    private const AUTHENTICATION_PROVIDER_SERVICE  = 'oro_oauth2_server.security.authentication_provider';
    private const CUSTOMER_VISITOR_MANAGER_SERVICE = 'oro_customer.customer_visitor_manager';
    private const WEBSITE_MANAGER_SERVICE          = 'oro_website.manager';

    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $definition = new ChildDefinition(self::AUTHENTICATION_PROVIDER_SERVICE);
        if ($this->isVisitorFirewall($config)) {
            $definition->setClass(VisitorOAuth2Provider::class)
                ->addArgument(new Reference(self::WEBSITE_MANAGER_SERVICE));
        }

        $providerId = self::AUTHENTICATION_PROVIDER_SERVICE . '.' . $id;
        $container
            ->setDefinition($providerId, $definition)
            ->replaceArgument(0, new Reference($this->getUserProvider($container, $id, $config, $userProvider)))
            ->replaceArgument(1, $id);

        $listenerId = self::FIREWALL_LISTENER_SERVICE . '.' . $id;
        $container
            ->setDefinition($listenerId, new ChildDefinition(self::FIREWALL_LISTENER_SERVICE))
            ->replaceArgument(3, $id);

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition()
    {
        return 'pre_auth';
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'oauth2';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
                ->booleanNode('anonymous_customer_user')->defaultValue(false)->end()
            ->end();
    }

    /**
     * If the firewall should support visitors, decorates the given user provider and returns it's name.
     * Otherwise, returns the given user provider.
     *
     * @param ContainerBuilder $container
     * @param string           $id
     * @param array            $config
     * @param string           $userProvider
     *
     * @return string
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
            [new Reference($userProvider), new Reference(self::CUSTOMER_VISITOR_MANAGER_SERVICE)]
        );
        $definition->setPrivate(true);

        $userProviderId = 'oro_oauth2_server.security.visitor_user_provider.' . $id;
        $container->setDefinition($userProviderId, $definition);

        return $userProviderId;
    }

    /**
     * @param array $config
     *
     * @return bool
     */
    private function isVisitorFirewall(array $config): bool
    {
        return
            class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')
            && $config['anonymous_customer_user'] === true;
    }
}
