<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Entity;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * The implementation of the user entity for "league/oauth2-server" library.
 */
class UserEntity implements UserEntityInterface
{
    use EntityTrait;
}
