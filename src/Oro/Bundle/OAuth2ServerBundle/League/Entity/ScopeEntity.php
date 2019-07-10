<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * The implementation of the scope entity for "league/oauth2-server" library.
 */
class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait, ScopeTrait;
}
