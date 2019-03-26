<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\NotificationBundle\Provider\AdditionalEmailAssociationProviderInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Provides additional associations related to OAuth 2.0 client for email notifications.
 */
class ClientAdditionalEmailAssociationProvider implements AdditionalEmailAssociationProviderInterface
{
    /** @var array */
    private $ownerEntityClasses;

    /** @var TranslatorInterface */
    private $translator;

    /** @var ConfigProvider */
    private $entityConfigProvider;

    /** @var ManagerRegistry */
    private $registry;

    /**
     * @param array               $ownerEntityClasses
     * @param TranslatorInterface $translator
     * @param ConfigProvider      $entityConfigProvider
     * @param ManagerRegistry     $registry
     */
    public function __construct(
        array $ownerEntityClasses,
        TranslatorInterface $translator,
        ConfigProvider $entityConfigProvider,
        ManagerRegistry $registry
    ) {
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->translator = $translator;
        $this->entityConfigProvider = $entityConfigProvider;
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociations(string $entityClass): array
    {
        if (!is_a($entityClass, Client::class, true)) {
            return [];
        }

        $result = [];
        foreach ($this->ownerEntityClasses as $fieldName => $className) {
            $result[$fieldName] = [
                $this->translator->trans($this->entityConfigProvider->getConfig($className)->get('label')),
                $className
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isAssociationSupported($entity, string $associationName): bool
    {
        return
            $entity instanceof Client
            && isset($this->ownerEntityClasses[$associationName]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationValue($entity, string $associationName)
    {
        /** @var Client $entity */

        if ($entity->getOwnerEntityClass() !== $this->ownerEntityClasses[$associationName]) {
            return null;
        }

        return $this->registry->getRepository($entity->getOwnerEntityClass())
            ->find($entity->getOwnerEntityId());
    }
}
