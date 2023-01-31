<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_NODE = 'oro_oauth2_server';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);
        $rootNode = $treeBuilder->getRootNode();

        $node = $rootNode->children();
        $this->appendAuthorizationServer($node);
        $this->appendResourceServer($node);

        return $treeBuilder;
    }

    private function appendAuthorizationServer(NodeBuilder $builder): void
    {
        $children = $builder
            ->arrayNode('authorization_server')
                ->addDefaultsIfNotSet()
                ->children();

        $children
            ->integerNode('access_token_lifetime')
                ->info('The lifetime in seconds of the access token.')
                ->min(0)
                ->defaultValue(3600) // 1 hour
            ->end()
            ->integerNode('refresh_token_lifetime')
                ->info('The lifetime in seconds of the refresh token.')
                ->min(0)
                ->defaultValue(18144000) // 30 days
            ->end()
            ->integerNode('auth_code_lifetime')
                ->info('The lifetime in seconds of the authorization code.')
                ->min(0)
                ->defaultValue(600) // 10 minutes
            ->end()
            ->booleanNode('enable_refresh_token')
                ->info('Determines if the refresh token grant is enabled.')
                ->defaultTrue()
            ->end()
            ->booleanNode('enable_auth_code')
                ->info('Determines if the authorization code grant is enabled.')
                ->defaultTrue()
            ->end()
            ->scalarNode('private_key')
                ->info(
                    'The full path to the private key file that is used to sign JWT tokens.'
                    . ' How to generate a private key:'
                    . ' https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys.'
                )
                ->example('/var/oauth/private.key')
                ->defaultValue('%kernel.project_dir%/var/oauth_private.key')
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('encryption_key')
                ->info(
                    'The string that is used to encrypt refresh token and authorization token payload.'
                    . ' How to generate an encryption key:'
                    . ' https://oauth2.thephpleague.com/installation/#string-password.'
                )
                ->defaultValue('%kernel.secret%')
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('cors')
                ->info('The configuration of CORS requests.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('preflight_max_age')
                        ->info('The amount of seconds the user agent is allowed to cache CORS preflight requests.')
                        ->defaultValue(600)
                        ->min(0)
                    ->end()
                    ->arrayNode('allow_origins')
                        ->info('The list of origins that are allowed to send CORS requests.')
                        ->example(['https://foo.com', 'https://bar.com'])
                        ->prototype('scalar')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendResourceServer(NodeBuilder $builder): void
    {
        $children = $builder
            ->arrayNode('resource_server')
                ->addDefaultsIfNotSet()
                ->children();

        $children
             ->scalarNode('public_key')
                ->info(
                    'The full path to the public key file that is used to verify JWT tokens.'
                    . ' How to generate a public key:'
                    . ' https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys.'
                )
                ->example('/var/oauth/public.key')
                ->defaultValue('%kernel.project_dir%/var/oauth_public.key')
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('oauth_firewalls')
                ->info('The list of security firewalls for which OAuth 2.0 authorization should be enabled.')
                ->prototype('scalar')
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }
}
