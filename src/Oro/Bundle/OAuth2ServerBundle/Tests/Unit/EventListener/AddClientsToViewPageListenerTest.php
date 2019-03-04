<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityBundle\ORM\EntityIdAccessor;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\EventListener\AddClientsToViewPageListener;
use Oro\Bundle\UIBundle\Event\BeforeViewRenderEvent;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Translation\TranslatorInterface;

class AddClientsToViewPageListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $translator;

    /** @var EntityIdAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $entityIdAccessor;

    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var AddClientsToViewPageListener */
    private $listener;

    protected function setUp()
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->entityIdAccessor = $this->createMock(EntityIdAccessor::class);
        $this->clientManager = $this->createMock(ClientManager::class);

        $this->listener = new AddClientsToViewPageListener(
            [User::class],
            $this->translator,
            $this->entityIdAccessor,
            $this->clientManager
        );
    }

    public function testAddOAuth2ClientsForNotSupportedOwner()
    {
        $environment = $this->createMock(\Twig_Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];

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

    public function testAddOAuth2ClientsForSupportedOwner()
    {
        $environment = $this->createMock(\Twig_Environment::class);
        $data = ['dataBlocks' => [['title' => 'test']]];
        $entity = $this->createMock(User::class);
        $entityClass = User::class;
        $entityId = 123;
        $creationGranted = true;
        $clientsTitle = 'Clients';
        $clientsData = 'clients data';

        $this->clientManager->expects(self::once())
            ->method('isCreationGranted')
            ->with($entityClass)
            ->willReturn($creationGranted);
        $this->entityIdAccessor->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($entity))
            ->willReturn($entityId);
        $environment->expects(self::once())
            ->method('render')
            ->with(
                'OroOAuth2ServerBundle:Client:clients.html.twig',
                [
                    'creationGranted' => $creationGranted,
                    'entityClass'     => $entityClass,
                    'entityId'        => $entityId
                ]
            )
            ->willReturn($clientsData);
        $this->translator->expects(self::once())
            ->method('trans')
            ->with('oro.oauth2server.clients')
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
