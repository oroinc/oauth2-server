<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(OroOAuth2ServerExtension::ALIAS);

        $node = $rootNode->children();
        $this->appendAuthorizationServer($node);

        return $treeBuilder;
    }

    /**
     * @param NodeBuilder $builder
     */
    private function appendAuthorizationServer(NodeBuilder $builder): void
    {
        $children = $builder
            ->arrayNode('authorization_server')
                ->prototype('array')
                    ->children();

        $children
            ->integerNode('access_token_lifetime')
                ->info('The lifetime in seconds of the access token.')
                ->min(0)
                ->defaultValue(3600)
            ->end();
    }
}
