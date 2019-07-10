<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint can be used to check if OAuth 2.0 client name is unique for a specific organization
 * and owner (if it is defined for the client).
 */
class UniqueClientName extends Constraint
{
    /** @var string */
    public $message = 'oro.oauth2server.client.name.unique.message';

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
