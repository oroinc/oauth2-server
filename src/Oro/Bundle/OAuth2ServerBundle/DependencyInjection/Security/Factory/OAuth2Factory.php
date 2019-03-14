<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The factory to configure OAuth 2.0 authentication listener.
 */
class OAuth2Factory implements SecurityFactoryInterface
{
    private const FIREWALL_LISTENER_SERVICE       = 'oro_oauth2_server.security.firewall_listener';
    private const AUTHENTICATION_PROVIDER_SERVICE = 'oro_oauth2_server.security.authentication_provider';

    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = self::AUTHENTICATION_PROVIDER_SERVICE . '.' . $id;
        $container
            ->setDefinition($providerId, new ChildDefinition(self::AUTHENTICATION_PROVIDER_SERVICE))
            ->replaceArgument(0, new Reference($userProvider))
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
    }
}
