<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Validator\Constraints;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\UniqueClientName;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\UniqueClientNameValidator;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\ReflectionUtil;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueClientNameValidatorTest extends ConstraintValidatorTestCase
{
    /** @var ManagerRegistry */
    private $doctrine;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function createValidator()
    {
        return new UniqueClientNameValidator($this->doctrine);
    }

    /**
     * @param Organization $organization
     * @param string       $name
     * @param bool         $isFrontend
     * @param string|null  $ownerEntityClass
     * @param int|null     $ownerEntityId
     * @param int|null     $clientEntityId
     *
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function expectGetValidationQueryBuilder(
        $organization,
        $name,
        $isFrontend,
        $ownerEntityClass = null,
        $ownerEntityId = null,
        $clientEntityId = null
    ) {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(Client::class)
            ->willReturn($em);
        $qb = $this->createMock(QueryBuilder::class);
        $em->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $qb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('from')
            ->with(Client::class, 'e')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('where')
            ->with('e.organization = :organization AND e.name = :name')
            ->willReturnSelf();
        if (!$ownerEntityClass && null === $clientEntityId) {
            $qb->expects(self::exactly(3))
                ->method('setParameter')
                ->withConsecutive(
                    ['organization', $organization],
                    ['name', $name],
                    ['frontend', $isFrontend]
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf()
                );
            $qb->expects(self::exactly(2))
                ->method('andWhere')
                ->withConsecutive(
                    ['e.ownerEntityClass is null AND e.ownerEntityId is null'],
                    ['e.frontend = :frontend']
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf()
                );
        } elseif (!$ownerEntityClass) {
            $qb->expects(self::once())
                ->method('andWhere')
                ->with('e.id <> :id')
                ->willReturnSelf();
            $qb->expects(self::exactly(3))
                ->method('setParameter')
                ->withConsecutive(
                    ['organization', $organization],
                    ['name', $name],
                    ['id', $clientEntityId]
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf()
                );
        } elseif (null === $clientEntityId) {
            $qb->expects(self::once())
                ->method('andWhere')
                ->with('e.ownerEntityClass = :ownerEntityClass AND e.ownerEntityId = :ownerEntityId')
                ->willReturnSelf();
            $qb->expects(self::exactly(4))
                ->method('setParameter')
                ->withConsecutive(
                    ['organization', $organization],
                    ['name', $name],
                    ['ownerEntityClass', $ownerEntityClass],
                    ['ownerEntityId', $ownerEntityId]
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf()
                );
        } else {
            $qb->expects(self::exactly(2))
                ->method('andWhere')
                ->withConsecutive(
                    ['e.ownerEntityClass = :ownerEntityClass AND e.ownerEntityId = :ownerEntityId'],
                    ['e.id <> :id']
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf()
                );
            $qb->expects(self::exactly(5))
                ->method('setParameter')
                ->withConsecutive(
                    ['organization', $organization],
                    ['name', $name],
                    ['ownerEntityClass', $ownerEntityClass],
                    ['ownerEntityId', $ownerEntityId],
                    ['id', $clientEntityId]
                )
                ->willReturnOnConsecutiveCalls(
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf(),
                    self::returnSelf()
                );
        }

        return $qb;
    }

    /**
     * @param Organization $organization
     * @param string       $name
     * @param bool         $isFrontend
     * @param array        $rows
     * @param string|null  $ownerEntityClass
     * @param int|null     $ownerEntityId
     */
    private function expectValidationQuery(
        $organization,
        $name,
        $isFrontend,
        $rows,
        $ownerEntityClass = null,
        $ownerEntityId = null
    ) {
        $qb = $this->expectGetValidationQueryBuilder(
            $organization,
            $name,
            $isFrontend,
            $ownerEntityClass,
            $ownerEntityId
        );
        $query = $this->createMock(AbstractQuery::class);
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn($rows);
    }

    public function testInvalidConstraint()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new Client(), new NotBlank());
    }

    public function testInvalidValue()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new UniqueClientName());
    }

    public function testNameIsNull()
    {
        $client = new Client();

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNameIsEmpty()
    {
        $client = new Client();
        $client->setName('');

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNoOrganization()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The organization must be defined.');

        $client = new Client();
        $client->setName('test');

        $this->validator->validate($client, new UniqueClientName());
    }

    public function testNoOwnerEntityClass()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectValidationQuery(
            $client->getOrganization(),
            $client->getName(),
            $client->isFrontend(),
            []
        );

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNameIsUniqueForNewClient()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectValidationQuery(
            $client->getOrganization(),
            $client->getName(),
            $client->isFrontend(),
            [],
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId()
        );

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNameIsUniqueForExistingClient()
    {
        $client = new Client();
        ReflectionUtil::setId($client, 1);
        $client->setName('test');
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $client->setOrganization($this->createMock(Organization::class));

        $qb = $this->expectGetValidationQueryBuilder(
            $client->getOrganization(),
            $client->getName(),
            $client->isFrontend(),
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId(),
            $client->getId()
        );
        $query = $this->createMock(AbstractQuery::class);
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn([]);

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNameIsNotUnique()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectValidationQuery(
            $client->getOrganization(),
            $client->getName(),
            $client->isFrontend(),
            [['id' => 1]],
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId()
        );

        $constraint = new UniqueClientName();
        $this->validator->validate($client, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameters(['{{ name }}' => $client->getName()])
            ->atPath('property.path.name')
            ->assertRaised();
    }

    public function testNameIsNotUniqueForClientWithoutOwner()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectValidationQuery(
            $client->getOrganization(),
            $client->getName(),
            $client->isFrontend(),
            [['id' => 1]],
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId()
        );

        $constraint = new UniqueClientName();
        $this->validator->validate($client, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameters(['{{ name }}' => $client->getName()])
            ->atPath('property.path.name')
            ->assertRaised();
    }
}
