<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\ConfigProviderMock;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientAdditionalEmailAssociationProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ClientAdditionalEmailAssociationProviderTest extends TestCase
{
    private ConfigProviderMock $entityConfigProvider;
    private ManagerRegistry&MockObject $doctrine;
    private ClientAdditionalEmailAssociationProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityConfigProvider = new ConfigProviderMock($this->createMock(ConfigManager::class), 'entity');
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(function ($value) {
                return 'translated_' . $value;
            });

        $this->provider = new ClientAdditionalEmailAssociationProvider(
            [
                'user'         => User::class,
                'organization' => Organization::class
            ],
            $translator,
            $this->entityConfigProvider,
            $this->doctrine
        );
    }

    public function testGetAssociationsForNotClientEntity(): void
    {
        self::assertEquals([], $this->provider->getAssociations(User::class));
    }

    public function testGetAssociations(): void
    {
        $this->entityConfigProvider->addEntityConfig(User::class, ['label' => 'user_label']);
        $this->entityConfigProvider->addEntityConfig(Organization::class, ['label' => 'organization_label']);

        self::assertEquals(
            [
                'user'         => ['label' => 'translated_user_label', 'target_class' => User::class],
                'organization' => ['label' => 'translated_organization_label', 'target_class' => Organization::class]
            ],
            $this->provider->getAssociations(Client::class)
        );
    }

    public function testIsAssociationSupportedWithNotSupportedEntity(): void
    {
        self::assertFalse($this->provider->isAssociationSupported(new User(), 'test'));
    }

    public function testIsAssociationSupportedWithNotSupportedAssociation(): void
    {
        self::assertFalse($this->provider->isAssociationSupported(new Client(), 'test'));
    }

    public function testIsAssociationSupportedWithSupportedAssociation(): void
    {
        self::assertTrue($this->provider->isAssociationSupported(new Client(), 'user'));
    }

    public function testGetAssociationValueOnWrongOwnerType(): void
    {
        $entity = new Client();
        $entity->setOwnerEntity(Organization::class, 2);

        self::assertNull($this->provider->getAssociationValue($entity, 'user'));
    }

    public function testGetAssociationValueOnCorrectOwnerType(): void
    {
        $entity = new Client();
        $ownerId = 123;
        $entity->setOwnerEntity(User::class, $ownerId);

        $expectedResult = new User();

        $repo = $this->createMock(EntityRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repo);
        $repo->expects(self::once())
            ->method('find')
            ->with($ownerId)
            ->willReturn($expectedResult);

        self::assertEquals($expectedResult, $this->provider->getAssociationValue($entity, 'user'));
    }
}
