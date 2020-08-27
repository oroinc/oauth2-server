<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Fixtures\Entity\ExtendedOrganization;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\AbstractUser;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientOwnerOrganizationsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var TokenAccessorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var ClientOwnerOrganizationsProvider */
    private $organizationsProvider;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);

        $this->organizationsProvider = new ClientOwnerOrganizationsProvider(
            $this->doctrine,
            $this->tokenAccessor
        );
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

    public function testIsMultiOrganizationSupportedWhenNoOrganizationInSecurityContext()
    {
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn(null);

        self::assertFalse($this->organizationsProvider->isMultiOrganizationSupported());
    }

    public function testIsMultiOrganizationSupportedForCommunityEdition()
    {
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());

        self::assertFalse($this->organizationsProvider->isMultiOrganizationSupported());
    }

    public function testIsMultiOrganizationSupportedForEnterpriseEdition()
    {
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new ExtendedOrganization());

        self::assertTrue($this->organizationsProvider->isMultiOrganizationSupported());
    }

    public function testGetClientOwnerOrganizationsForCommunityEdition()
    {
        $ownerEntityClass = 'Test\OwnerEntity';
        $ownerEntityId = 123;
        $currentOrganization = new Organization();

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($currentOrganization);
        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        self::assertSame(
            [$currentOrganization],
            $this->organizationsProvider->getClientOwnerOrganizations($ownerEntityClass, $ownerEntityId)
        );
    }

    public function testGetClientOwnerOrganizationsForEnterpriseEditionAndNotGlobalOrganization()
    {
        $ownerEntityClass = 'Test\OwnerEntity';
        $ownerEntityId = 123;
        $currentOrganization = new ExtendedOrganization();
        $currentOrganization->setIsGlobal(false);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($currentOrganization);
        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        self::assertSame(
            [$currentOrganization],
            $this->organizationsProvider->getClientOwnerOrganizations($ownerEntityClass, $ownerEntityId)
        );
    }

    public function testGetClientOwnerOrganizationsForGlobalOrganizationAndOwnerIsOrganizationAwareUser()
    {
        $ownerEntityClass = 'Test\OwnerEntity';
        $ownerEntityId = 123;
        $owner = $this->createMock(AbstractUser::class);
        $organization1 = new ExtendedOrganization();
        $organization2 = new ExtendedOrganization();

        $currentOrganization = new ExtendedOrganization();
        $currentOrganization->setIsGlobal(true);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($currentOrganization);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($ownerEntityClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);
        $owner->expects(self::once())
            ->method('getOrganizations')
            ->with(true)
            ->willReturn(new ArrayCollection([$organization1, $organization2]));

        self::assertSame(
            [$organization1, $organization2],
            $this->organizationsProvider->getClientOwnerOrganizations($ownerEntityClass, $ownerEntityId)
        );
    }

    public function testGetClientOwnerOrganizationsForGlobalOrganizationAndOwnerIsNotOrganizationAwareUser()
    {
        $ownerEntityClass = 'Test\OwnerEntity';
        $ownerEntityId = 123;
        $owner = new \stdClass();
        $currentOrganization = new ExtendedOrganization();
        $currentOrganization->setIsGlobal(true);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($currentOrganization);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($ownerEntityClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        self::assertSame(
            [$currentOrganization],
            $this->organizationsProvider->getClientOwnerOrganizations($ownerEntityClass, $ownerEntityId)
        );
    }

    public function testGetClientOwnerOrganizationsForGlobalOrganizationAndOwnerDoesNotExist()
    {
        $ownerEntityClass = 'Test\OwnerEntity';
        $ownerEntityId = 123;
        $currentOrganization = new ExtendedOrganization();
        $currentOrganization->setIsGlobal(true);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($currentOrganization);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($ownerEntityClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn(null);

        self::assertSame(
            [$currentOrganization],
            $this->organizationsProvider->getClientOwnerOrganizations($ownerEntityClass, $ownerEntityId)
        );
    }

    public function testIsOrganizationSelectorRequiredForEmptyOrganizations()
    {
        $organizations = [];

        $this->tokenAccessor->expects(self::never())
            ->method('getOrganizationId');

        self::assertFalse($this->organizationsProvider->isOrganizationSelectorRequired($organizations));
    }

    public function testIsOrganizationSelectorRequiredForSeveralOrganizations()
    {
        $organizations = [
            $this->getOrganization(10, 'org1'),
            $this->getOrganization(20, 'org2')
        ];

        $this->tokenAccessor->expects(self::never())
            ->method('getOrganizationId');

        self::assertTrue($this->organizationsProvider->isOrganizationSelectorRequired($organizations));
    }

    public function testIsOrganizationSelectorRequiredForOneOrganizationAndItEqualsToOrganizationInSecurityContext()
    {
        $organizations = [
            $this->getOrganization(10, 'org1')
        ];

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(10);

        self::assertFalse($this->organizationsProvider->isOrganizationSelectorRequired($organizations));
    }

    public function testIsOrganizationSelectorRequiredForOneOrganizationAndItNotEqualToOrganizationInSecurityContext()
    {
        $organizations = [
            $this->getOrganization(10, 'org1')
        ];

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(20);

        self::assertTrue($this->organizationsProvider->isOrganizationSelectorRequired($organizations));
    }

    public function testSortOrganizations()
    {
        $organizations = [
            $this->getOrganization(10, 'org2'),
            $this->getOrganization(20, 'org1'),
            $this->getOrganization(30, 'org4'),
            $this->getOrganization(40, 'org3')
        ];

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($organizations[2]);

        self::assertEquals(
            ['org4', 'org1', 'org2', 'org3'],
            array_map(
                function (Organization $org) {
                    return $org->getName();
                },
                $this->organizationsProvider->sortOrganizations($organizations)
            )
        );
    }
}
