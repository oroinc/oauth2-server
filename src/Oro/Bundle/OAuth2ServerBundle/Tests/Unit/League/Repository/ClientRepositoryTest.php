<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ClientRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientRepositoryTest extends TestCase
{
    private ClientManager&MockObject $clientManager;
    private PasswordHasherFactoryInterface&MockObject $passwordHasherFactory;
    private ApiFeatureChecker&MockObject $featureChecker;
    private OAuthUserChecker&MockObject $userChecker;
    private ManagerRegistry&MockObject $doctrine;
    private ClientRepository $clientRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->featureChecker = $this->createMock(ApiFeatureChecker::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->clientRepository = new ClientRepository(
            $this->clientManager,
            $this->passwordHasherFactory,
            $this->featureChecker,
            $this->userChecker,
            $this->doctrine
        );
    }

    private function getOrganization(bool $enabled = true): Organization
    {
        $organization = new Organization();
        $organization->setEnabled($enabled);

        return $organization;
    }

    public function testGetClientEntityWhenClientNotFound(): void
    {
        $clientIdentifier = 'test_client';

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn(null);

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenNotActiveClient(): void
    {
        $clientIdentifier = 'test_client';
        $client = new Client();
        $client->setActive(false);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenGrantSupported(): void
    {
        $clientIdentifier = 'test_client';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenGrantNotSupported(): void
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['another_grant'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenPasswordGrantShouldSupportRefreshTokenGrant(): void
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['password'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenAuthorizationCodeGrantShouldSupportRefreshTokenGrant(): void
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['authorization_code'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenOrganizationIsDisabled(): void
    {
        $clientIdentifier = 'test_client';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization(false));

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testValidateClientWhenClientNotFound(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn(null);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenNotActiveClient(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $client = new Client();
        $client->setActive(false);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenClientHasOwnerAndOwnerIsNotActive(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock(User::class);
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setOrganization($this->getOrganization());
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with(self::identicalTo($client))
            ->willReturn(true);

        $ownerRepository = $this->createMock(EntityRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with($ownerEntityClass)
            ->willReturn($ownerRepository);
        $ownerRepository->expects(self::once())
            ->method('find')
            ->with($ownerEntityId)
            ->willReturn($owner);
        $this->userChecker->expects(self::once())
            ->method('checkUser')
            ->with(self::identicalTo($owner))
            ->willThrowException(new OAuthServerException('User is disabled', 1, 'user_disabled'));

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenGrantSupported(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenGrantNotSupported(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenPasswordGrantShouldSupportRefreshTokenGrant(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $clientGrants = ['password'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenAuthorizationCodeGrantShouldSupportRefreshTokenGrant(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $clientGrants = ['authorization_code'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenOrganizationIsDisabled(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization(false));

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->passwordHasherFactory->expects(self::never())
            ->method('getPasswordHasher');

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenInvalidSecret(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $client->setGrants($clientGrants);
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(false);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenValidSecret(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenValidSecretForFrontend(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $client->setFrontend(true);
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenValidSecretAndClientHasOwnerAndOwnerIsActive(): void
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $clientRedirectUris = ['test_url'];
        $ownerEntityClass = User::class;
        $ownerEntityId = 123;
        $owner = $this->createMock(User::class);
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $secretHasher = $this->createMock(PasswordHasherInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $ownerRepository = $this->createMock(EntityRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with($ownerEntityClass)
            ->willReturn($ownerRepository);
        $ownerRepository->expects(self::once())
            ->method('find')
            ->with($ownerEntityId)
            ->willReturn($owner);
        $this->userChecker->expects(self::once())
            ->method('checkUser')
            ->with(self::identicalTo($owner));
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($client::class)
            ->willReturn($secretHasher);
        $secretHasher->expects(self::once())
            ->method('verify')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }
}
