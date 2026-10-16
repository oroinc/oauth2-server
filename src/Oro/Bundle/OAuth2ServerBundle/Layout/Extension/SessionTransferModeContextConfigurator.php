<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Layout\Extension;

use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferModeProvider;
use Oro\Component\Layout\ContextConfiguratorInterface;
use Oro\Component\Layout\ContextInterface;

/**
 * Adds the `is_in_session_transfer_mode` option to the layout context.
 */
class SessionTransferModeContextConfigurator implements ContextConfiguratorInterface
{
    public const string OPTION_NAME = 'is_in_session_transfer_mode';

    public function __construct(private readonly SessionTransferModeProvider $sessionTransferModeProvider)
    {
    }

    #[\Override]
    public function configureContext(ContextInterface $context): void
    {
        $context->getResolver()
            ->setRequired([self::OPTION_NAME])
            ->setAllowedTypes(self::OPTION_NAME, ['bool']);

        $context->set(self::OPTION_NAME, $this->sessionTransferModeProvider->isInSessionTransferMode());
    }
}
