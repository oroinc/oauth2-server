<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint can be used to check if OAuth 2.0 client with "authorization_code" grant
 * has at least one redirect URI.
 */
class ClientRedirectUris extends Constraint
{
    /** @var string */
    public $message = 'oro.oauth2server.client.redirectUris.not_empty.message';

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
