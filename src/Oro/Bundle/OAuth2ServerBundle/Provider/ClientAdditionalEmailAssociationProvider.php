<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\NotificationBundle\Provider\AdditionalEmailAssociationProviderInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Contracts\Translation\TranslatorInterface;

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
    private $doctrine;

    public function __construct(
        array $ownerEntityClasses,
        TranslatorInterface $translator,
        ConfigProvider $entityConfigProvider,
        ManagerRegistry $doctrine
    ) {
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->translator = $translator;
        $this->entityConfigProvider = $entityConfigProvider;
        $this->doctrine = $doctrine;
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
        foreach ($this->ownerEntityClasses as $fieldName => $ownerEntityClass) {
            $result[$fieldName] = [
                'label'        => $this->translator->trans($this->getAssociationLabel($ownerEntityClass)),
                'target_class' => $ownerEntityClass
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

        return $this->doctrine->getRepository($entity->getOwnerEntityClass())
            ->find($entity->getOwnerEntityId());
    }

    private function getAssociationLabel(string $ownerEntityClass): string
    {
        return (string) $this->entityConfigProvider->getConfig($ownerEntityClass)->get('label');
    }
}
