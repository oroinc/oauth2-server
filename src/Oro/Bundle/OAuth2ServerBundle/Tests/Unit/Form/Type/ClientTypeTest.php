<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\FormBundle\Form\Extension\TooltipFormExtension;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\ClientType;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\TranslationBundle\Form\Extension\TranslatableChoiceTypeExtension;
use Oro\Bundle\TranslationBundle\Translation\Translator;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\TypeTestCase;

class ClientTypeTest extends TypeTestCase
{
    /** @var ClientOwnerOrganizationsProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $organizationsProvider;

    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    protected function setUp()
    {
        $this->organizationsProvider = $this->createMock(ClientOwnerOrganizationsProvider::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtensions()
    {
        return [
            new PreloadedExtension(
                [
                    new ClientType($this->organizationsProvider),
                    new EntityType($this->doctrine)
                ],
                [
                    FormType::class   => [
                        new TooltipFormExtension(
                            $this->createMock(ConfigProvider::class),
                            $this->createMock(Translator::class)
                        )
                    ],
                    ChoiceType::class => [
                        new TranslatableChoiceTypeExtension()
                    ]
                ]
            )
        ];
    }

    /**
     * @param Client $client
     * @param array  $options
     *
     * @return FormInterface
     */
    private function createClientType(Client $client, array $options = []): FormInterface
    {
        return $this->factory->create(ClientType::class, $client, $options);
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return Organization|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getOrganization($id, $name)
    {
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($id);
        $organization->expects(self::any())
            ->method('getName')
            ->willReturn($name);

        return $organization;
    }

    public function testSubmitForNewClientWhenMultiOrganizationIsNotSupported()
    {
        $client = new Client();
        $submittedData = [
            'name'   => 'test name',
            'active' => 1,
            'grants' => 'grant2'
        ];

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);
        $this->organizationsProvider->expects(self::never())
            ->method('getClientOwnerOrganizations');
        $this->organizationsProvider->expects(self::never())
            ->method('isOrganizationSelectorRequired');

        $form = $this->createClientType($client, ['grant_types' => ['grant1', 'grant2']]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant2'], $client->getGrants());
    }

    public function testSubmitForNewClientWhenMultiOrganizationIsSupportedAndClientOwnerBelongsToOneOrganizationOnly()
    {
        $client = new Client();
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $submittedData = [
            'name'   => 'test name',
            'active' => 1,
            'grants' => 'grant2'
        ];
        $organization1 = $this->getOrganization(10, 'org1');

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('getClientOwnerOrganizations')
            ->with($client->getOwnerEntityClass(), $client->getOwnerEntityId())
            ->willReturn([$organization1]);
        $this->organizationsProvider->expects(self::once())
            ->method('isOrganizationSelectorRequired')
            ->with([$organization1])
            ->willReturn(false);

        $form = $this->createClientType($client, ['grant_types' => ['grant1', 'grant2']]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant2'], $client->getGrants());
    }

    public function testSubmitForNewClientWhenMultiOrganizationIsSupportedAndClientOwnerBelongsToSeveralOrganizations()
    {
        $client = new Client();
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $submittedData = [
            'organization' => 20,
            'name'         => 'test name',
            'active'       => 1,
            'grants'       => 'grant2'
        ];
        $organization1 = $this->getOrganization(10, 'org1');
        $organization2 = $this->getOrganization(20, 'org2');

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('getClientOwnerOrganizations')
            ->with($client->getOwnerEntityClass(), $client->getOwnerEntityId())
            ->willReturn([$organization1, $organization2]);
        $this->organizationsProvider->expects(self::once())
            ->method('isOrganizationSelectorRequired')
            ->with([$organization1, $organization2])
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('sortOrganizations')
            ->with([$organization1, $organization2])
            ->willReturn([$organization1, $organization2]);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(Organization::class)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('getClassMetadata')
            ->with(Organization::class)
            ->willReturn($classMetadata);
        $em->expects(self::any())
            ->method('contains')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);
        $classMetadata->expects(self::once())
            ->method('getTypeOfField')
            ->with('id')
            ->willReturn('integer');
        $classMetadata->expects(self::any())
            ->method('getIdentifierValues')
            ->willReturnMap([
                [$organization1, ['id' => $organization1->getId()]],
                [$organization2, ['id' => $organization2->getId()]]
            ]);

        $form = $this->createClientType($client, ['grant_types' => ['grant1', 'grant2']]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->has('organization'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant2'], $client->getGrants());
        self::assertSame($organization2, $client->getOrganization());
    }

    public function testSubmitForExistingClient()
    {
        $client = new Client();
        $client->setGrants(['grant1']);
        $idProp = (new \ReflectionClass($client))->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($client, 1);
        $client->setOwnerEntity('Test\OwnerEntity', 123);
        $submittedData = [
            'name'   => 'test name',
            'active' => 1,
            'grants' => 'grant2'
        ];

        $this->organizationsProvider->expects(self::never())
            ->method('isMultiOrganizationSupported');
        $this->organizationsProvider->expects(self::never())
            ->method('getClientOwnerOrganizations');
        $this->organizationsProvider->expects(self::never())
            ->method('isOrganizationSelectorRequired');

        $form = $this->createClientType($client, ['grant_types' => ['grant1', 'grant2']]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant1'], $client->getGrants());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     * @expectedExceptionMessage The required option "grant_types" is missing.
     */
    public function testSubmitWhenNoGrantTypes()
    {
        $this->createClientType(new Client());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "grant_types" must not be empty.
     */
    public function testSubmitWhenEmptyGrantTypes()
    {
        $this->createClientType(new Client(), ['grant_types' => []]);
    }

    public function testSubmitWhenOneGrantTypeAndVisibleGrantTypes()
    {
        $client = new Client();
        $submittedData = [
            'name'   => 'test name',
            'active' => 1
        ];

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);

        $form = $this->createClientType($client, ['grant_types' => ['grant1'], 'show_grants' => true]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));
        self::assertTrue($form->has('grants'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant1'], $client->getGrants());
    }

    public function testSubmitWhenOneGrantTypeAndInvisibleGrantTypes()
    {
        $client = new Client();
        $submittedData = [
            'name'   => 'test name',
            'active' => 1,
            'grants' => 'grant1'
        ];

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);

        $form = $this->createClientType($client, ['grant_types' => ['grant1']]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));
        self::assertTrue($form->has('grants'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant1'], $client->getGrants());
    }

    public function testSubmitWhenSeveralGrantTypeAndInvisibleGrantTypes()
    {
        $client = new Client();
        $submittedData = [
            'name'   => 'test name',
            'active' => 1
        ];

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);

        $form = $this->createClientType($client, ['grant_types' => ['grant1', 'grant2'], 'show_grants' => false]);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->has('organization'));
        self::assertFalse($form->has('grants'));

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertNull($client->getGrants());
    }
}
