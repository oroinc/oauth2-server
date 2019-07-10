<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * The implementation of the access token entity for "league/oauth2-server" library.
 */
class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait, AccessTokenTrait, TokenEntityTrait;
}
