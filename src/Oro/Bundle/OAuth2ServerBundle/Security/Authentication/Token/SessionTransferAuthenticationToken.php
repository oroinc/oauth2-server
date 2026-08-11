<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\SecurityBundle\Authentication\Token\UsernamePasswordOrganizationToken;

/**
 * The authentication token that is used when an user has been logged via Session Transfer.
 */
class SessionTransferAuthenticationToken extends UsernamePasswordOrganizationToken
{
}
