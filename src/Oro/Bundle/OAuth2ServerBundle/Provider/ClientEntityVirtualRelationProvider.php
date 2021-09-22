<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds virtual relations for OAuth 2.0 client entity.
 */
class ClientEntityVirtualRelationProvider implements VirtualRelationProviderInterface
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var string[] */
    private $ownerEntityClasses;

    /** @var TranslatorInterface */
    private $translator;

    /** @var ConfigProvider */
    private $entityConfigProvider;

    /**
     * @param string[]            $ownerEntityClasses
     * @param DoctrineHelper      $doctrineHelper
     * @param TranslatorInterface $translator
     * @param ConfigProvider      $entityConfigProvider
     */
    public function __construct(
        array $ownerEntityClasses,
        DoctrineHelper $doctrineHelper,
        TranslatorInterface $translator,
        ConfigProvider $entityConfigProvider
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->translator = $translator;
        $this->entityConfigProvider = $entityConfigProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function isVirtualRelation($className, $fieldName)
    {
        return
            is_a($className, Client::class, true)
            && isset($this->ownerEntityClasses[$fieldName]);
    }

    /**
     * {@inheritdoc}
     */
    public function getVirtualRelations($className)
    {
        if (!is_a($className, Client::class, true)) {
            return [];
        }

        $result = [];
        foreach ($this->ownerEntityClasses as $associationName => $ownerEntityClass) {
            $result[$associationName] = [
                'label'               => $this->translator->trans($this->getAssociationLabel($ownerEntityClass)),
                'relation_type'       => RelationType::MANY_TO_ONE,
                'related_entity_name' => $ownerEntityClass,
                'target_join_alias'   => $associationName,
                'query'               => [
                    'join' => [
                        'left' => [
                            [
                                'join'          => $ownerEntityClass,
                                'alias'         => $associationName,
                                'conditionType' => Join::WITH,
                                'condition'     => sprintf(
                                    'entity.ownerEntityClass = \'%s\' AND entity.ownerEntityId = %s.%s',
                                    $ownerEntityClass,
                                    $associationName,
                                    $this->doctrineHelper->getSingleEntityIdentifierFieldName($ownerEntityClass)
                                )
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getVirtualRelationQuery($className, $fieldName)
    {
        $relations = $this->getVirtualRelations($className);
        if (array_key_exists($fieldName, $relations)) {
            return $relations[$fieldName]['query'];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getTargetJoinAlias($className, $fieldName, $selectFieldName = null)
    {
        return $fieldName;
    }

    private function getAssociationLabel(string $ownerEntityClass): string
    {
        return (string) $this->entityConfigProvider->getConfig($ownerEntityClass)->get('label');
    }
}
