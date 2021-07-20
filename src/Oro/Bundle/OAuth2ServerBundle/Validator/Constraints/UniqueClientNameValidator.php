<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that OAuth 2.0 client name is unique for a specific organization
 * and owner (if it is defined for the client).
 */
class UniqueClientNameValidator extends ConstraintValidator
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof UniqueClientName) {
            throw new UnexpectedTypeException($constraint, UniqueClientName::class);
        }
        if (!$value instanceof Client) {
            throw new UnexpectedTypeException($value, Client::class);
        }

        $name = $value->getName();
        if (!$name) {
            return;
        }

        $organization = $value->getOrganization();
        if (null === $organization) {
            throw new RuntimeException('The organization must be defined.');
        }

        $ownerEntityClass = $value->getOwnerEntityClass();
        $ownerEntityId = $value->getOwnerEntityId();
        $clientEntityId = $value->getId();
        $isFrontend = $value->isFrontend();
        if ($this->isClientExist(
            $organization,
            $name,
            $isFrontend,
            $ownerEntityClass,
            $ownerEntityId,
            $clientEntityId
        )) {
            $this->context->buildViolation($constraint->message, ['{{ name }}' => $name])
                ->atPath('name')
                ->addViolation();
        }
    }

    private function isClientExist(
        Organization $organization,
        string $name,
        bool $isFrontend,
        ?string $ownerEntityClass,
        ?int $ownerEntityId,
        ?int $id
    ): bool {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('e.id')
            ->from(Client::class, 'e')
            ->setMaxResults(1)
            ->where('e.organization = :organization AND e.name = :name')
            ->setParameter('organization', $organization)
            ->setParameter('name', $name);
        if ($ownerEntityClass) {
            $qb
                ->andWhere('e.ownerEntityClass = :ownerEntityClass AND e.ownerEntityId = :ownerEntityId')
                ->setParameter('ownerEntityClass', $ownerEntityClass)
                ->setParameter('ownerEntityId', $ownerEntityId);
        } else {
            $qb->andWhere('e.ownerEntityClass is null AND e.ownerEntityId is null')
                ->andWhere('e.frontend = :frontend')
                ->setParameter('frontend', $isFrontend);
        }
        if (null !== $id) {
            $qb->andWhere('e.id <> :id')->setParameter('id', $id);
        }
        $rows = $qb->getQuery()->getArrayResult();

        return !empty($rows);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(Client::class);
    }
}
