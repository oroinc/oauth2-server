<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Entity;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * The implementation of the refresh token entity for "league/oauth2-server" library.
 */
class RefreshTokenEntity implements RefreshTokenEntityInterface
{
    use EntityTrait, RefreshTokenTrait;
}
