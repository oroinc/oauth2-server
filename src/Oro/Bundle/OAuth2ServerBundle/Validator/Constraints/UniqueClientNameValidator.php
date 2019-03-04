<?php

namespace Oro\Bundle\OAuth2ServerBundle\Validator\Constraints;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that OAuth 2.0 client name is unique for a specific owner and organization.
 */
class UniqueClientNameValidator extends ConstraintValidator
{
    /** @var ManagerRegistry */
    private $doctrine;

    /**
     * @param ManagerRegistry $doctrine
     */
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
        if (null === $ownerEntityClass) {
            throw new RuntimeException('The owner entity class must be defined.');
        }
        $ownerEntityId = $value->getOwnerEntityId();
        if (null === $ownerEntityId) {
            throw new RuntimeException('The owner entity ID must be defined.');
        }

        if ($this->isClientExist($ownerEntityClass, $ownerEntityId, $organization, $name, $value->getId())) {
            $this->context->buildViolation($constraint->message, ['{{ name }}' => $name])
                ->atPath('name')
                ->addViolation();
        }
    }

    /**
     * @param string       $ownerEntityClass
     * @param int          $ownerEntityId
     * @param Organization $organization
     * @param string       $name
     * @param int?null     $id
     *
     * @return bool
     */
    private function isClientExist(
        string $ownerEntityClass,
        int $ownerEntityId,
        Organization $organization,
        string $name,
        ?int $id
    ): bool {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('e.id')
            ->from(Client::class, 'e')
            ->setMaxResults(1)
            ->where(
                'e.ownerEntityClass = :ownerEntityClass AND e.ownerEntityId = :ownerEntityId'
                . ' AND e.organization = :organization AND e.name= :name'
            )
            ->setParameters([
                'ownerEntityClass' => $ownerEntityClass,
                'ownerEntityId'    => $ownerEntityId,
                'organization'     => $organization,
                'name'             => $name
            ]);
        if (null !== $id) {
            $qb->andWhere('e.id <> :id')->setParameter('id', $id);
        }
        $rows = $qb->getQuery()->getArrayResult();

        return !empty($rows);
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(Client::class);
    }
}
