<?php

namespace Oro\Bundle\OAuth2ServerBundle;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\OroOAuth2ServerExtension;
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
}
