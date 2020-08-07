<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint can be used to check if OAuth 2.0 client with "client_credentials" grant
 * is assigned to a user.
 */
class ClientOwner extends Constraint
{
    /** @var string */
    public $message = 'oro.oauth2server.client.owner.not_null.message';

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
