<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Skips websocket notifications tracking for OAuth entities.
 */
class SkipSyncTrackingPass implements CompilerPassInterface
{
    private const SERVICE_ID = 'oro_sync.event_listener.doctrine_tag';

    /** @var array */
    private $skippedEntityClasses = [
        AccessToken::class,
        AuthCode::class,
        Client::class,
        RefreshToken::class
    ];

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(self::SERVICE_ID);

        foreach ($this->skippedEntityClasses as $entityClass) {
            $definition->addMethodCall('markSkipped', [$entityClass]);
        }
    }
}
