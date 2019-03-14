<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\EntityBundle\ORM\EntityIdAccessor;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use Oro\Bundle\UIBundle\Event\BeforeViewRenderEvent;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * The listener that adds block with OAuth 2.0 clients grid
 * on the view page of entities that can own the clients.
 */
class AddClientsToViewPageListener
{
    /** @var string[] */
    private $ownerEntityClasses;

    /** @var TranslatorInterface */
    private $translator;

    /** @var EntityIdAccessor */
    private $entityIdAccessor;

    /** @var ClientManager */
    private $clientManager;

    /** @var EncryptionKeysExistenceChecker */
    private $encryptionKeysExistenceChecker;

    /**
     * @param string[]                       $ownerEntityClasses
     * @param TranslatorInterface            $translator
     * @param EntityIdAccessor               $entityIdAccessor
     * @param ClientManager                  $clientManager
     * @param EncryptionKeysExistenceChecker $encryptionKeysExistenceChecker
     */
    public function __construct(
        array $ownerEntityClasses,
        TranslatorInterface $translator,
        EntityIdAccessor $entityIdAccessor,
        ClientManager $clientManager,
        EncryptionKeysExistenceChecker $encryptionKeysExistenceChecker
    ) {
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->translator = $translator;
        $this->entityIdAccessor = $entityIdAccessor;
        $this->clientManager = $clientManager;
        $this->encryptionKeysExistenceChecker = $encryptionKeysExistenceChecker;
    }

    /**
     * @param BeforeViewRenderEvent $event
     */
    public function addOAuth2Clients(BeforeViewRenderEvent $event): void
    {
        $entity = $event->getEntity();
        $entityClass = $this->getEntityClassIfItCanOwnClient($entity);
        if (!$entityClass) {
            return;
        }

        $entityId = $this->entityIdAccessor->getIdentifier($entity);
        $clientsData = $event->getTwigEnvironment()->render(
            'OroOAuth2ServerBundle:Client:clients.html.twig',
            [
                'encryptionKeysExist' =>
                    $this->encryptionKeysExistenceChecker->isPrivateKeyExist()
                    && $this->encryptionKeysExistenceChecker->isPublicKeyExist(),
                'creationGranted'     => $this->clientManager->isCreationGranted($entityClass, $entityId),
                'entityClass'         => $entityClass,
                'entityId'            => $entityId
            ]
        );

        $data = $event->getData();
        $data['dataBlocks'][] = [
            'title'     => $this->translator->trans('oro.oauth2server.client.entity_plural_label'),
            'priority'  => 1500, // render after all other blocks
            'subblocks' => [['data' => [$clientsData]]]
        ];
        $event->setData($data);
    }

    /**
     * @param object $entity
     *
     * @return string|null
     */
    private function getEntityClassIfItCanOwnClient($entity): ?string
    {
        $entityClass = null;
        foreach ($this->ownerEntityClasses as $ownerEntityClass) {
            if ($entity instanceof $ownerEntityClass) {
                $entityClass = $ownerEntityClass;
                break;
            }
        }

        return $entityClass;
    }
}
