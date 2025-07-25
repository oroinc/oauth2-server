<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\Twig\Sandbox\TemplateData;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientEntityVariableProcessor;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientEntityVariableProcessorTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private TemplateData&MockObject $data;
    private ClientEntityVariableProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->data = $this->createMock(TemplateData::class);

        $this->processor = new ClientEntityVariableProcessor($this->doctrine);
    }

    public function testProcessForOwnerEntity(): void
    {
        $ownerClass = User::class;
        $ownerId = 123;
        $owner = new User();
        $entity = new Client();
        $entity->setOwnerEntity($ownerClass, $ownerId);

        $variable = 'entity.user';
        $parentVariable = 'entity';
        $definition = ['related_entity_name' => $ownerClass];

        $this->data->expects(self::once())
            ->method('getParentVariablePath')
            ->with($variable)
            ->willReturn($parentVariable);
        $this->data->expects(self::once())
            ->method('getEntityVariable')
            ->with($parentVariable)
            ->willReturn($entity);
        $this->data->expects(self::once())
            ->method('setComputedVariable')
            ->with($variable, self::identicalTo($owner));

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($ownerClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('find')
            ->with($ownerClass, $ownerId)
            ->willReturn($owner);

        $this->processor->process($variable, $definition, $this->data);
    }

    public function testProcessWhenOwnerEntityIsDifferentThanDefinedInVariable(): void
    {
        $ownerClass = User::class;
        $ownerId = 123;
        $entity = new Client();
        $entity->setOwnerEntity($ownerClass, $ownerId);

        $variable = 'entity.user';
        $parentVariable = 'entity';
        $definition = ['related_entity_name' => 'Test\AnotherOwner'];

        $this->data->expects(self::once())
            ->method('getParentVariablePath')
            ->with($variable)
            ->willReturn($parentVariable);
        $this->data->expects(self::once())
            ->method('getEntityVariable')
            ->with($parentVariable)
            ->willReturn($entity);
        $this->data->expects(self::once())
            ->method('setComputedVariable')
            ->with($variable, self::isNull());

        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        $this->processor->process($variable, $definition, $this->data);
    }

    public function testProcessWhenOwnerEntityIsNull(): void
    {
        $entity = new Client();

        $variable = 'entity.user';
        $parentVariable = 'entity';
        $definition = ['related_entity_name' => User::class];

        $this->data->expects(self::once())
            ->method('getParentVariablePath')
            ->with($variable)
            ->willReturn($parentVariable);
        $this->data->expects(self::once())
            ->method('getEntityVariable')
            ->with($parentVariable)
            ->willReturn($entity);
        $this->data->expects(self::once())
            ->method('setComputedVariable')
            ->with($variable, self::isNull());

        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        $this->processor->process($variable, $definition, $this->data);
    }
}
