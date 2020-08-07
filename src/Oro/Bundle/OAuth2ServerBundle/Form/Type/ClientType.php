<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

/**
 * The form for OAuth 2.0 client without owner field.
 */
class ClientType extends AbstractClientType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_oauth2_client';
    }
}
