<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Validator\Constraints;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\UniqueClientName;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\UniqueClientNameValidator;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueClientNameValidatorTest extends ConstraintValidatorTestCase
{
    /** @var ManagerRegistry */
    private $doctrine;

    protected function setUp()
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
     * @param string       $ownerEntityClass
     * @param int          $ownerEntityId
     * @param Organization $organization
     * @param string       $name
     *
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGetValidationQueryBuilder($ownerEntityClass, $ownerEntityId, $organization, $name)
    {
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
            ->with(
                'e.ownerEntityClass = :ownerEntityClass AND e.ownerEntityId = :ownerEntityId'
                . ' AND e.organization = :organization AND e.name= :name'
            )
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameters')
            ->with([
                'ownerEntityClass' => $ownerEntityClass,
                'ownerEntityId'    => $ownerEntityId,
                'organization'     => $organization,
                'name'             => $name
            ])
            ->willReturnSelf();

        return $qb;
    }

    /**
     * @param string       $ownerEntityClass
     * @param int          $ownerEntityId
     * @param Organization $organization
     * @param string       $name
     * @param array        $rows
     */
    private function expectValidationQuery($ownerEntityClass, $ownerEntityId, $organization, $name, $rows)
    {
        $qb = $this->expectGetValidationQueryBuilder($ownerEntityClass, $ownerEntityId, $organization, $name);
        $query = $this->createMock(AbstractQuery::class);
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn($rows);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidConstraint()
    {
        $this->validator->validate(new Client(), new NotBlank());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidValue()
    {
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

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     * @expectedExceptionMessage The organization must be defined.
     */
    public function testNoOrganization()
    {
        $client = new Client();
        $client->setName('test');

        $this->validator->validate($client, new UniqueClientName());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     * @expectedExceptionMessage The owner entity class must be defined.
     */
    public function testNoOwnerEntityClass()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOrganization($this->createMock(Organization::class));

        $this->validator->validate($client, new UniqueClientName());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     * @expectedExceptionMessage The owner entity ID must be defined.
     */
    public function testNoOwnerEntityId()
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('getName')
            ->willReturn('test');
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn($this->createMock(Organization::class));
        $client->expects(self::once())
            ->method('getOwnerEntityClass')
            ->willReturn('Test\OwnerEntity');
        $client->expects(self::once())
            ->method('getOwnerEntityId')
            ->willReturn(null);

        $this->validator->validate($client, new UniqueClientName());
    }

    public function testNameIsUniqueForNewClient()
    {
        $client = new Client();
        $client->setName('test');
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectValidationQuery(
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId(),
            $client->getOrganization(),
            $client->getName(),
            []
        );

        $this->validator->validate($client, new UniqueClientName());

        $this->assertNoViolation();
    }

    public function testNameIsUniqueForExistingClient()
    {
        $client = new Client();
        $idProp = (new \ReflectionClass($client))->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($client, 1);
        $client->setName('test');
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $client->setOrganization($this->createMock(Organization::class));

        $qb = $this->expectGetValidationQueryBuilder(
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId(),
            $client->getOrganization(),
            $client->getName()
        );
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.id <> :id')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('id', $client->getId())
            ->willReturnSelf();
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
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId(),
            $client->getOrganization(),
            $client->getName(),
            [['id' => 1]]
        );

        $constraint = new UniqueClientName();
        $this->validator->validate($client, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameters(['{{ name }}' => $client->getName()])
            ->atPath('property.path.name')
            ->assertRaised();
    }
}
