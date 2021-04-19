<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ClientRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var
     * @deprecated
     * ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject
     */
    private $doctrine;

    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var EncoderFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $encoderFactory;

    /** @var ApiFeatureChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $featureChecker;

    /** @var ClientRepository */
    private $clientRepository;

    protected function setUp()
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->featureChecker = $this->createMock(ApiFeatureChecker::class);

        $this->clientRepository = new ClientRepository(
            $this->doctrine,
            $this->encoderFactory,
            $this->featureChecker
        );
        $this->clientRepository->setClientManager($this->clientManager);
    }

    /**
     * @param bool $enabled
     *
     * @return Organization
     */
    private function getOrganization(bool $enabled = true): Organization
    {
        $organization = new Organization();
        $organization->setEnabled($enabled);

        return $organization;
    }

    public function testClientNotFound()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = true;

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn(null);

        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertNull($clientEntity);
    }

    public function testNotActiveClient()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = true;
        $client = new Client();
        $client->setActive(false);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertNull($clientEntity);
    }

    public function testInvalidSecret()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = true;
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(false);

        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertNull($clientEntity);
    }

    public function testValidSecret()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = true;
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization());
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals($clientRedirectUris, $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testValidSecretForFrontend()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = true;
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setRedirectUris([]);
        $client->setOrganization($this->getOrganization());
        $client->setFrontend(true);
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertTrue($clientEntity->isFrontend());
    }

    public function testNoValidationOfSecret()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = false;
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals($clientRedirectUris, $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGrantSupported()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = false;
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

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGrantNotSupported()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = false;
        $clientGrants = ['another_grant'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertNull($clientEntity);
    }

    public function testPasswordGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $mustValidateSecret = false;
        $clientGrants = ['password'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testAuthorizationCodeGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $mustValidateSecret = false;
        $clientGrants = ['authorization_code'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testDisabledOrganization()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $mustValidateSecret = false;
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization(false));

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity(
            $clientIdentifier,
            $grantType,
            $clientSecret,
            $mustValidateSecret
        );

        self::assertNull($clientEntity);
    }
}
