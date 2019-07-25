<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\SystemClientType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that OAuth 2.0 client with client_credentials grant have assigned user information.
 */
class ClientOwnerValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ClientOwner) {
            throw new UnexpectedTypeException($constraint, ClientOwner::class);
        }
        if (!$value instanceof Client) {
            throw new UnexpectedTypeException($value, Client::class);
        }

        $grants = $value->getGrants();
        if (null === $grants) {
            return;
        }

        $ownerClass = $value->getOwnerEntityClass();
        $ownerId = $value->getOwnerEntityId();

        if ((!$ownerClass || !$ownerId) && in_array('client_credentials', $grants, true)) {
            $this->context->buildViolation($constraint->message)
                ->atPath(SystemClientType::OWNER_FIELD)
                ->addViolation();
        }
    }
}
