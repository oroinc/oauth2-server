<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Component\Testing\ReflectionUtil;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
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

    /** @var ClientManager */
    private $clientManager;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->clientManager = new ClientManager(
            $this->doctrine,
            $this->encoderFactory,
            $this->tokenAccessor,
            $this->authorizationChecker
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
            ReflectionUtil::setId($client, $id);
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

    public function testIsViewGrantedWithoutObject()
    {
        $isGranted = true;

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('VIEW', 'entity:' . Client::class)
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isViewGranted());
    }

    public function testIsViewGranted()
    {
        $isGranted = true;
        $client = $this->getClient();

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('VIEW', self::identicalTo($client))
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isViewGranted($client));
    }

    public function testIsCreationGranted()
    {
        $isGranted = true;

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('CREATE', 'entity:' . Client::class)
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isCreationGranted());
    }

    public function testIsModificationGranted()
    {
        $isGranted = true;
        $client = $this->getClient();

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', self::identicalTo($client))
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isModificationGranted($client));
    }

    public function testIsDeletionGranted()
    {
        $isGranted = true;
        $client = $this->getClient();

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('DELETE', self::identicalTo($client))
            ->willReturn($isGranted);

        self::assertSame($isGranted, $this->clientManager->isDeletionGranted($client));
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

    public function testGetClient()
    {
        $clientId = 'test_client';
        $client = $this->getClient(12);
        $client->setIdentifier($clientId);

        $repo = $this->createMock(ObjectRepository::class);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(Client::class)
            ->willReturn($repo);
        $repo->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $clientId])
            ->willReturn($client);

        self::assertSame($client, $this->clientManager->getClient($clientId));
        //the second itteration with same client id should not init additional db queries
        self::assertSame($client, $this->clientManager->getClient($clientId));
    }
}
