<?php

namespace Oro\Bundle\OAuth2ServerBundle;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler\SkipSyncTrackingPass;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\OroOAuth2ServerExtension;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory\OAuth2Factory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The OAuth2ServerBundle bundle class.
 */
class OroOAuth2ServerBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (!$this->extension) {
            $this->extension = new OroOAuth2ServerExtension();
        }

        return $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new OAuth2Factory());

        $container->addCompilerPass(new SkipSyncTrackingPass());
    }
}
