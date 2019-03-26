<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\Twig\Sandbox\TemplateData;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientEntityVariableProcessor;
use Oro\Bundle\UserBundle\Entity\User;

class ClientEntityVariableProcessorTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var ClientEntityVariableProcessor */
    private $processor;

    protected function setUp()
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->processor = new ClientEntityVariableProcessor($this->doctrine);
    }

    /**
     * @param array $data
     *
     * @return TemplateData
     */
    private function getTemplateData(array $data): TemplateData
    {
        return new TemplateData($data, 'system', 'entity', 'computed');
    }

    public function testProcessForOwnerEntity()
    {
        $ownerClass = User::class;
        $ownerId = 123;
        $owner = new User();
        $entity = new Client();
        $entity->setOwnerEntity($ownerClass, $ownerId);

        $variable = 'entity.user';
        $definition = ['related_entity_name' => $ownerClass];
        $data = $this->getTemplateData(['entity' => $entity]);

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($ownerClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('find')
            ->with($ownerClass, $ownerId)
            ->willReturn($owner);

        $this->processor->process($variable, $definition, $data);

        self::assertSame($owner, $data->getComputedVariable($variable));
    }

    public function testProcessWhenOwnerEntityIsDifferentThanDefinedInVariable()
    {
        $ownerClass = User::class;
        $ownerId = 123;
        $entity = new Client();
        $entity->setOwnerEntity($ownerClass, $ownerId);

        $variable = 'entity.user';
        $definition = ['related_entity_name' => 'Test\AnotherOwner'];
        $data = $this->getTemplateData(['entity' => $entity]);

        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        $this->processor->process($variable, $definition, $data);

        self::assertNull($data->getComputedVariable($variable));
    }

    public function testProcessWhenOwnerEntityIsNull()
    {
        $entity = new Client();

        $variable = 'entity.user';
        $definition = ['related_entity_name' => User::class];
        $data = $this->getTemplateData(['entity' => $entity]);

        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        $this->processor->process($variable, $definition, $data);

        self::assertNull($data->getComputedVariable($variable));
    }
}
