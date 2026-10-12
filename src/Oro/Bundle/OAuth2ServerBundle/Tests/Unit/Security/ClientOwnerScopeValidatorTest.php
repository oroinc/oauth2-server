<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Owner\CustomerAwareOwnerTreeInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\ClientOwnerScopeValidator;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientOwnerScopeValidatorTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private UserLoaderInterface&MockObject $userLoader;
    private CustomerAwareOwnerTreeInterface&MockObject $ownerTreeProvider;
    private ClientOwnerScopeValidator $validator;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->ownerTreeProvider = $this->createMock(CustomerAwareOwnerTreeInterface::class);
        $this->validator = new ClientOwnerScopeValidator(
            $this->doctrine,
            $this->userLoader,
            $this->ownerTreeProvider
        );
    }

    /**
     * @dataProvider tokenWithoutUserIdentifierDataProvider
     */
    public function testValidWhenTokenDoesNotRepresentUser(?string $userIdentifier): void
    {
        $this->doctrine->expects(self::never())
            ->method('getRepository');

        $client = new Client();
        $client->setFrontend(true);

        self::assertTrue($this->validator->isValid($client, $userIdentifier));
    }

    public static function tokenWithoutUserIdentifierDataProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => ['']
        ];
    }

    public function testValidWhenClientDoesNotHaveOwner(): void
    {
        $this->doctrine->expects(self::never())
            ->method('getRepository');

        $client = new Client();
        $client->setFrontend(true);

        self::assertTrue($this->validator->isValid($client, 'user@example.org'));
    }

    public function testInvalidWhenClientDoesNotHaveOrganization(): void
    {
        $this->doctrine->expects(self::never())
            ->method('getRepository');

        $client = (new Client())->setOwnerEntity(CustomerUser::class, 10);
        $client->setFrontend(true);

        self::assertFalse($this->validator->isValid($client, 'user@example.org'));
    }

    public function testInvalidWhenClientOwnerIsNotCustomerUser(): void
    {
        $organization = $this->createMock(Organization::class);
        $organization->method('getId')->willReturn(42);
        $client = (new Client())
            ->setOrganization($organization)
            ->setOwnerEntity(CustomerUser::class, 10);

        $repository = $this->createMock(ObjectRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(CustomerUser::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($this->createMock(User::class));
        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with('user@example.org')
            ->willReturn($this->createMock(CustomerUser::class));
        $this->ownerTreeProvider->expects(self::never())
            ->method('getTreeByBusinessUnit');

        self::assertFalse($this->validator->isValid($client, 'user@example.org'));
    }

    public function testInvalidWhenTokenUserIsNotCustomerUser(): void
    {
        $organization = $this->createMock(Organization::class);
        $organization->method('getId')->willReturn(42);
        $client = (new Client())
            ->setOrganization($organization)
            ->setOwnerEntity(CustomerUser::class, 10);

        $repository = $this->createMock(ObjectRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(CustomerUser::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($this->createMock(CustomerUser::class));
        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with('user@example.org')
            ->willReturn($this->createMock(User::class));
        $this->ownerTreeProvider->expects(self::never())
            ->method('getTreeByBusinessUnit');

        self::assertFalse($this->validator->isValid($client, 'user@example.org'));
    }

    public function testInvalidWhenClientOwnerDoesNotHaveCustomer(): void
    {
        $organization = $this->createMock(Organization::class);
        $organization->method('getId')->willReturn(42);
        $client = (new Client())
            ->setOrganization($organization)
            ->setOwnerEntity(CustomerUser::class, 10);

        $owner = $this->createMock(CustomerUser::class);
        $owner->expects(self::once())
            ->method('getCustomer')
            ->willReturn(null);
        $repository = $this->createMock(ObjectRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(CustomerUser::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($owner);
        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with('user@example.org')
            ->willReturn($this->createMock(CustomerUser::class));
        $this->ownerTreeProvider->expects(self::never())
            ->method('getTreeByBusinessUnit');

        self::assertFalse($this->validator->isValid($client, 'user@example.org'));
    }

    /**
     * @dataProvider validScopeDataProvider
     */
    public function testValidScope(array $userCustomerIds, bool $expectedResult): void
    {
        $organization = $this->createMock(Organization::class);
        $organization->method('getId')->willReturn(42);
        $client = (new Client())
            ->setOrganization($organization)
            ->setOwnerEntity(CustomerUser::class, 10);
        $client->setFrontend(true);

        $ownerCustomer = $this->createMock(Customer::class);
        $owner = $this->createMock(CustomerUser::class);
        $owner->method('getId')->willReturn(10);
        $owner->method('getCustomer')->willReturn($ownerCustomer);
        $user = $this->createMock(CustomerUser::class);
        $user->method('getId')->willReturn(20);

        $repository = $this->createMock(ObjectRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(CustomerUser::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($owner);
        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with('user@example.org')
            ->willReturn($user);

        $tree = $this->createMock(OwnerTreeInterface::class);
        $this->ownerTreeProvider->expects(self::once())
            ->method('getTreeByBusinessUnit')
            ->with(self::identicalTo($ownerCustomer))
            ->willReturn($tree);
        $tree->expects(self::once())
            ->method('getUserSubordinateBusinessUnitIds')
            ->with(10, 42)
            ->willReturn([100, 101, 102]);
        $tree->expects(self::once())
            ->method('getUserBusinessUnitIds')
            ->with(20, 42)
            ->willReturn($userCustomerIds);

        self::assertSame($expectedResult, $this->validator->isValid($client, 'user@example.org'));
    }

    public static function validScopeDataProvider(): array
    {
        return [
            'the owner customer' => [[100], true],
            'a subordinate customer' => [[102], true],
            'an unrelated customer' => [[200], false],
            'a user outside of the owner tree' => [[], false]
        ];
    }
}
