<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that OAuth 2.0 client with "authorization_code" grant that allows logout
 * has at least one post-logout redirect URI.
 */
class ClientPostLogoutRedirectUrisValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ClientPostLogoutRedirectUris) {
            throw new UnexpectedTypeException($constraint, ClientPostLogoutRedirectUris::class);
        }
        if (!$value instanceof Client) {
            throw new UnexpectedTypeException($value, Client::class);
        }

        if (!$value->isLogoutAllowed() || !\in_array('authorization_code', $value->getGrants() ?? [], true)) {
            return;
        }

        if (!$value->getPostLogoutRedirectUris()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('postLogoutRedirectUris')
                ->addViolation();
        }
    }
}
