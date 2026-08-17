<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;

/**
 * The authentication token that is used when a customer visitor has been logged via Session Transfer.
 */
class SessionTransferCustomerVisitorAuthenticationToken extends AnonymousCustomerUserToken
{
}
