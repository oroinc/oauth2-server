<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint can be used to check if OAuth 2.0 client with "authorization_code" grant
 * that allows logout has at least one post-logout redirect URI.
 */
class ClientPostLogoutRedirectUris extends Constraint
{
    public string $message = 'oro.oauth2server.client.postLogoutRedirectUris.not_empty.message';

    #[\Override]
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
