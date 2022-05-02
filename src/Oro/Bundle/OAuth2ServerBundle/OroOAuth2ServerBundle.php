<?php

namespace Oro\Bundle\OAuth2ServerBundle;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler\AddSkippedLogAttemptsFirewalls;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler\SkipSyncTrackingPass;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\OroOAuth2ServerExtension;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory\OAuth2Factory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OroOAuth2ServerBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new OAuth2Factory());

        $container->addCompilerPass(new SkipSyncTrackingPass());
        $container->addCompilerPass(new AddSkippedLogAttemptsFirewalls());
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new OroOAuth2ServerExtension();
        }

        return $this->extension;
    }
}
