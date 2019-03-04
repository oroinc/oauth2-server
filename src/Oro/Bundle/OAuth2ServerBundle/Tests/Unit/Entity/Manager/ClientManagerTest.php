<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\EntityIdAccessor;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Acl\Domain\DomainObjectReference;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

class ClientManagerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var EncoderFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $encoderFactory;

    /** @var TokenAccessorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var AuthorizationCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $authorizationChecker;

    /** @var EntityOwnerAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $entityOwnerAccessor;

    /** @var EntityIdAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $entityIdAccessor;

    /** @var ClientManager */
    private $clientManager;

    protected function setUp()
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->entityOwnerAccessor = $this->createMock(EntityOwnerAccessor::class);
        $this->entityIdAccessor = $this->createMock(EntityIdAccessor::class);

        $this->clientManager = new ClientManager(
            $this->doctrine,
            $this->encoderFactory,
            $this->tokenAccessor,
            $this->authorizationChecker,
            $this->entityOwnerAccessor,
            $this->entityIdAccessor
        );
    }

    /**
     * @param int|null $id
     *
     * @return Client
     */
    private function getClient($id = null)
    {
        $client = new Client();
        if (null !== $id) {
            $idProp = (new \ReflectionClass($client))->getProperty('id');
            $idProp->setAccessible(true);
            $idProp->setValue($client, $id);
        }

        return $client;
    }

    /**
     * @param string|null $value
     * @param int         $maxLength
     */
    private static function assertRandomString($value, $maxLength)
    {
        self::assertNotNull($value);
        self::assertNotEmpty($value);
        self::assertTrue(strlen($value) >= (int)($maxLength * 0.6));
        self::assertTrue(strlen($value) <= $maxLength);
    }

    /**
     * @return EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGetEntityManager()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(Client::class)
            ->willReturn($em);

        return $em;
    }

    /**
     * @param Client      $client
     * @param string      $encodedSecret
     * @param string|null $plainPassword
     */
    private function expectEncodeSecret(Client $client, string $encodedSecret, $plainPassword = null)
    {
        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($encoder);
        $encoder->expects(self::once())
            ->method('encodePassword')
            ->with(
                $plainPassword ?? self::isType('string'),
                self::isType('string')
            )
            ->willReturn($encodedSecret);
    }

    /**
     * @return array
     */
    public static function isGrantedDataProvider()
    {
        return [
            'granted' => [true],
            'denied'  => [false]
        ];
    }

    public function testIsCreationGrantedWhenOwnerEntityEqualsToLoggedInUser()
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn($ownerEntityId);
        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        self::assertTrue($this->clientManager->isCreationGranted($ownerEntityClass, $ownerEntityId));
    }

    public function testIsModificationGrantedWhenOwnerEntityEqualsToLoggedInUser()
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn($ownerEntityId);
        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        self::assertTrue($this->clientManager->isModificationGranted($client));
    }

    public function testIsCreationGrantedWhenOwnerEntityIsNotExistingUser()
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn(null);
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        self::assertFalse($this->clientManager->isCreationGranted($ownerEntityClass, $ownerEntityId));
    }

    public function testIsModificationGrantedWhenOwnerEntityIsNotExistingUser()
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn(null);
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        self::assertFalse($this->clientManager->isModificationGranted($client));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsCreationGrantedWhenOwnerEntityIsUserButItDoesNotEqualToLoggedInUser($isGranted)
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', self::identicalTo($owner))
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isCreationGranted($ownerEntityClass, $ownerEntityId));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsModificationGrantedWhenClientEntityDoesNotHaveOrganization($isGranted)
    {
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', self::identicalTo($owner))
            ->willReturn($isGranted);

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsModificationGrantedWhenOwnerEntityHasSameOrganizationAsClientEntity($isGranted)
    {
        $organizationId = 234;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);
        $ownerOrganization = $this->createMock(Organization::class);
        $ownerOrganization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);
        $ownerOwner = new \stdClass();

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOwner')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOwner);
        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOrganization')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOrganization);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', self::identicalTo($owner))
            ->willReturn($isGranted);

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setOrganization($organization);
        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsModificationGrantedWhenOwnerEntityAndClientEntityHaveDifferentOrganizations($isGranted)
    {
        $organizationId = 234;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);
        $ownerOrganizationId = 345;
        $ownerOrganization = $this->createMock(Organization::class);
        $ownerOrganization->expects(self::any())
            ->method('getId')
            ->willReturn($ownerOrganizationId);
        $ownerOwnerId = 200;
        $ownerOwner = new \stdClass();

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::at(0))
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOwner')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOwner);
        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOrganization')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOrganization);
        $this->entityIdAccessor->expects(self::at(1))
            ->method('getIdentifier')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerEntityId);
        $this->entityIdAccessor->expects(self::at(2))
            ->method('getIdentifier')
            ->with(self::identicalTo($ownerOwner))
            ->willReturn($ownerOwnerId);
        $this->entityIdAccessor->expects(self::at(3))
            ->method('getIdentifier')
            ->with(self::identicalTo($ownerOrganization))
            ->willReturn($ownerOrganizationId);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with(
                'EDIT',
                new DomainObjectReference(get_class($owner), $ownerEntityId, $ownerOwnerId, $ownerOrganizationId)
            )
            ->willReturn($isGranted);

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setOrganization($organization);
        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsModificationGrantedWhenOwnerEntityHasOrgOwnershipAndSameOrgAsClientEntity($isGranted)
    {
        $organizationId = 234;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);
        $ownerOrganization = $this->createMock(Organization::class);
        $ownerOrganization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOwner')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOrganization);
        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOrganization')
            ->with(self::identicalTo($owner))
            ->willReturn(null);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', self::identicalTo($owner))
            ->willReturn($isGranted);

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setOrganization($organization);
        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    /**
     * @dataProvider isGrantedDataProvider
     */
    public function testIsModificationGrantedWhenOwnerEntityHasOrgOwnershipAndDifferentOrgThenClientEntity($isGranted)
    {
        $organizationId = 234;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);
        $ownerOrganizationId = 345;
        $ownerOrganization = $this->createMock(Organization::class);
        $ownerOrganization->expects(self::any())
            ->method('getId')
            ->willReturn($ownerOrganizationId);

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::at(0))
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOwner')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerOrganization);
        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOrganization')
            ->with(self::identicalTo($owner))
            ->willReturn(null);
        $this->entityIdAccessor->expects(self::at(1))
            ->method('getIdentifier')
            ->with(self::identicalTo($owner))
            ->willReturn($ownerEntityId);
        $this->entityIdAccessor->expects(self::at(2))
            ->method('getIdentifier')
            ->with(self::identicalTo($ownerOrganization))
            ->willReturn($ownerOrganizationId);
        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with(
                'EDIT',
                new DomainObjectReference(get_class($owner), $ownerEntityId, $ownerOrganizationId, null)
            )
            ->willReturn($isGranted);

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setOrganization($organization);
        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    public function testIsModificationGrantedWhenOwnerEntityDoesNotHaveOwnership()
    {
        $organizationId = 234;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($organizationId);

        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock($ownerEntityClass);

        $currentUser = $this->createMock(User::class);
        $this->tokenAccessor->expects(self::once())
            ->method('getUser')
            ->willReturn($currentUser);
        $this->entityIdAccessor->expects(self::at(0))
            ->method('getIdentifier')
            ->with(self::identicalTo($currentUser))
            ->willReturn(100);
        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('find')
            ->with($ownerEntityClass, $ownerEntityId)
            ->willReturn($owner);

        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOwner')
            ->with(self::identicalTo($owner))
            ->willReturn(null);
        $this->entityOwnerAccessor->expects(self::once())
            ->method('getOrganization')
            ->with(self::identicalTo($owner))
            ->willReturn(null);
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        $client = $this->getClient();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setOrganization($organization);
        self::assertTrue($this->clientManager->isModificationGranted($client));
    }

    public function testUpdateClientWithFlush()
    {
        $client = $this->getClient();
        $client->setOrganization($this->createMock(Organization::class));

        $this->expectEncodeSecret($client, 'test');

        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('persist')
            ->with(self::identicalTo($client));
        $em->expects(self::once())
            ->method('flush');

        $this->clientManager->updateClient($client);
    }

    public function testUpdateNewClient()
    {
        $client = $this->getClient();
        $organization = $this->createMock(Organization::class);
        $encodedSecret = 'encoded_secret';

        $this->expectEncodeSecret($client, $encodedSecret);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($organization);

        $this->clientManager->updateClient($client, false);

        self::assertRandomString($client->getIdentifier(), 32);
        self::assertRandomString($client->getPlainSecret(), 128);
        self::assertEquals($encodedSecret, $client->getSecret());
        self::assertRandomString($client->getSalt(), 50);
        self::assertSame($organization, $client->getOrganization());
        self::assertNull($client->getGrants());
        self::assertNull($client->getScopes());
        self::assertNull($client->getRedirectUris());
    }

    public function testUpdateExistingClient()
    {
        $client = $this->getClient(123);
        $client->setOrganization($this->createMock(Organization::class));

        $this->clientManager->updateClient($client, false);

        self::assertNull($client->getIdentifier());
        self::assertNull($client->getPlainSecret());
        self::assertNull($client->getSecret());
        self::assertNull($client->getSalt());
        self::assertNull($client->getGrants());
        self::assertNull($client->getScopes());
        self::assertNull($client->getRedirectUris());
    }

    public function testUpdateExistingClientWithPlainSecret()
    {
        $plainSecret = 'plain_secret';
        $encodedSecret = 'encoded_secret';
        $existingSalt = 'existing_salt';

        $client = $this->getClient(123);
        $client->setOrganization($this->createMock(Organization::class));
        $client->setPlainSecret($plainSecret);
        $client->setSecret('existing_secret', $existingSalt);

        $this->expectEncodeSecret($client, $encodedSecret, $plainSecret);

        $this->clientManager->updateClient($client, false);

        self::assertNull($client->getIdentifier());
        self::assertEquals($plainSecret, $client->getPlainSecret());
        self::assertEquals($encodedSecret, $client->getSecret());
        self::assertRandomString($client->getSalt(), 50);
        self::assertNotEquals($existingSalt, $client->getSalt());
        self::assertNull($client->getGrants());
        self::assertNull($client->getScopes());
        self::assertNull($client->getRedirectUris());
    }

    public function testUpdateClientWithEmptyArrayInScopes()
    {
        $client = $this->getClient(123);
        $client->setOrganization($this->createMock(Organization::class));
        $client->setScopes([]);

        $this->clientManager->updateClient($client, false);

        self::assertNull($client->getScopes());
    }

    public function testUpdateClientWithEmptyArrayInRedirectUris()
    {
        $client = $this->getClient(123);
        $client->setOrganization($this->createMock(Organization::class));
        $client->setRedirectUris([]);

        $this->clientManager->updateClient($client, false);

        self::assertNull($client->getRedirectUris());
    }

    public function testActivateClient()
    {
        $client = $this->getClient();
        $client->setActive(false);

        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('flush');

        $this->clientManager->activateClient($client);
        self::assertTrue($client->isActive());
    }

    public function testDeactivateClient()
    {
        $client = $this->getClient();
        $client->setActive(true);

        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('flush');

        $this->clientManager->deactivateClient($client);
        self::assertFalse($client->isActive());
    }

    public function testDeleteClient()
    {
        $client = $this->getClient();

        $em = $this->expectGetEntityManager();
        $em->expects(self::once())
            ->method('remove')
            ->with(self::identicalTo($client));
        $em->expects(self::once())
            ->method('flush');

        $this->clientManager->deleteClient($client);
    }
}
