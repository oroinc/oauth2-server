<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that OAuth 2.0 client with "authorization_code" grant has at least one redirect URI.
 */
class ClientRedirectUrisValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ClientRedirectUris) {
            throw new UnexpectedTypeException($constraint, ClientRedirectUris::class);
        }
        if (!$value instanceof Client) {
            throw new UnexpectedTypeException($value, Client::class);
        }

        $grants = $value->getGrants();
        if (null === $grants || !\in_array('authorization_code', $value->getGrants(), true)) {
            return;
        }

        if (!$value->getRedirectUris()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('redirectUris')
                ->addViolation();
        }
    }
}
