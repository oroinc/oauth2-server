<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityBundle\ORM\EntityIdAccessor;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\EventListener\AddClientsToViewPageListener;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use Oro\Bundle\UIBundle\Event\BeforeViewRenderEvent;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AddClientsToViewPageListenerTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private EntityIdAccessor&MockObject $entityIdAccessor;
    private ClientManager&MockObject $clientManager;
    private EncryptionKeysExistenceChecker&MockObject $encryptionKeysExistenceChecker;
    private ApiFeatureChecker&MockObject $featureChecker;
    private AddClientsToViewPageListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->entityIdAccessor = $this->createMock(EntityIdAccessor::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->encryptionKeysExistenceChecker = $this->createMock(EncryptionKeysExistenceChecker::class);
        $this->featureChecker = $this->createMock(ApiFeatureChecker::class);

        $this->listener = new AddClientsToViewPageListener(
            [User::class],
            $this->translator,
            $this->entityIdAccessor,
            $this->clientManager,
            $this->encryptionKeysExistenceChecker,
            $this->featureChecker
        );
    }

    public function testAddOAuth2ClientsForNotSupportedOwner(): void
    {
        $environment = $this->createMock(Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];

        $this->encryptionKeysExistenceChecker->expects(self::never())
            ->method('isPrivateKeyExist');
        $this->encryptionKeysExistenceChecker->expects(self::never())
            ->method('isPublicKeyExist');
        $this->clientManager->expects(self::never())
            ->method('isCreationGranted');
        $this->entityIdAccessor->expects(self::never())
            ->method('getIdentifier');
        $environment->expects(self::never())
            ->method('render');
        $this->translator->expects(self::never())
            ->method('trans');

        $event = new BeforeViewRenderEvent($environment, $data, new \stdClass());
        $this->listener->addOAuth2Clients($event);

        self::assertEquals($data, $event->getData());
    }

    public function testAddOAuth2ClientsWhenUserHasNoViewPermissionToOauthApplications(): void
    {
        $environment = $this->createMock(Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];
        $entity = $this->createMock(User::class);

        $this->clientManager->expects(self::once())
            ->method('isViewGranted')
            ->willReturn(false);

        $event = new BeforeViewRenderEvent($environment, $data, $entity);
        $this->listener->addOAuth2Clients($event);

        self::assertEquals($data, $event->getData());
    }

    public function testAddOAuth2ClientsForSupportedOwner(): void
    {
        $environment = $this->createMock(Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];
        $entity = $this->createMock(User::class);
        $entityClass = User::class;
        $entityId = 123;
        $creationGranted = true;
        $clientsTitle = 'Clients';
        $clientsData = 'clients data';

        $this->encryptionKeysExistenceChecker->expects(self::once())
            ->method('isPrivateKeyExist')
            ->willReturn(true);
        $this->encryptionKeysExistenceChecker->expects(self::once())
            ->method('isPublicKeyExist')
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClientOwnerClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isCreationGranted')
            ->willReturn($creationGranted);
        $this->clientManager->expects(self::once())
            ->method('isViewGranted')
            ->willReturn(true);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($entity))
            ->willReturn($entityId);
        $environment->expects(self::once())
            ->method('render')
            ->with(
                '@OroOAuth2Server/Client/clients.html.twig',
                [
                    'encryptionKeysExist' => true,
                    'creationGranted'     => $creationGranted,
                    'entityClass'         => $entityClass,
                    'entityId'            => $entityId
                ]
            )
            ->willReturn($clientsData);
        $this->translator->expects(self::once())
            ->method('trans')
            ->with('oro.oauth2server.client.entity_plural_label')
            ->willReturn($clientsTitle);

        $event = new BeforeViewRenderEvent($environment, $data, $entity);
        $this->listener->addOAuth2Clients($event);

        $expectedData = $data;
        $expectedData['dataBlocks'][] = [
            'title'     => $clientsTitle,
            'priority'  => 1500,
            'subblocks' => [['data' => [$clientsData]]]
        ];
        self::assertEquals($expectedData, $event->getData());
    }

    public function testAddOAuth2ClientsForSupportedOwnerWhenNoPublicKey(): void
    {
        $environment = $this->createMock(Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];
        $entity = $this->createMock(User::class);
        $entityClass = User::class;
        $entityId = 123;
        $creationGranted = true;
        $clientsTitle = 'Clients';
        $clientsData = 'clients data';

        $this->encryptionKeysExistenceChecker->expects(self::once())
            ->method('isPrivateKeyExist')
            ->willReturn(true);
        $this->encryptionKeysExistenceChecker->expects(self::once())
            ->method('isPublicKeyExist')
            ->willReturn(false);
        $this->clientManager->expects(self::once())
            ->method('isViewGranted')
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClientOwnerClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isCreationGranted')
            ->willReturn($creationGranted);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($entity))
            ->willReturn($entityId);
        $environment->expects(self::once())
            ->method('render')
            ->with(
                '@OroOAuth2Server/Client/clients.html.twig',
                [
                    'encryptionKeysExist' => false,
                    'creationGranted'     => $creationGranted,
                    'entityClass'         => $entityClass,
                    'entityId'            => $entityId
                ]
            )
            ->willReturn($clientsData);
        $this->translator->expects(self::once())
            ->method('trans')
            ->with('oro.oauth2server.client.entity_plural_label')
            ->willReturn($clientsTitle);

        $event = new BeforeViewRenderEvent($environment, $data, $entity);
        $this->listener->addOAuth2Clients($event);

        $expectedData = $data;
        $expectedData['dataBlocks'][] = [
            'title'     => $clientsTitle,
            'priority'  => 1500,
            'subblocks' => [['data' => [$clientsData]]]
        ];
        self::assertEquals($expectedData, $event->getData());
    }

    public function testAddOAuth2ClientsForSupportedOwnerWhenNoPrivateKey(): void
    {
        $environment = $this->createMock(Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];
        $entity = $this->createMock(User::class);
        $entityClass = User::class;
        $entityId = 123;
        $creationGranted = true;
        $clientsTitle = 'Clients';
        $clientsData = 'clients data';

        $this->encryptionKeysExistenceChecker->expects(self::once())
            ->method('isPrivateKeyExist')
            ->willReturn(false);
        $this->encryptionKeysExistenceChecker->expects(self::never())
            ->method('isPublicKeyExist');
        $this->clientManager->expects(self::once())
            ->method('isViewGranted')
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClientOwnerClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isCreationGranted')
            ->willReturn($creationGranted);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($entity))
            ->willReturn($entityId);
        $environment->expects(self::once())
            ->method('render')
            ->with(
                '@OroOAuth2Server/Client/clients.html.twig',
                [
                    'encryptionKeysExist' => false,
                    'creationGranted'     => $creationGranted,
                    'entityClass'         => $entityClass,
                    'entityId'            => $entityId
                ]
            )
            ->willReturn($clientsData);
        $this->translator->expects(self::once())
            ->method('trans')
            ->with('oro.oauth2server.client.entity_plural_label')
            ->willReturn($clientsTitle);

        $event = new BeforeViewRenderEvent($environment, $data, $entity);
        $this->listener->addOAuth2Clients($event);

        $expectedData = $data;
        $expectedData['dataBlocks'][] = [
            'title'     => $clientsTitle,
            'priority'  => 1500,
            'subblocks' => [['data' => [$clientsData]]]
        ];
        self::assertEquals($expectedData, $event->getData());
    }
}
