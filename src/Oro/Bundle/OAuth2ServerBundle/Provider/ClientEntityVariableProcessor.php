<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\Twig\Sandbox\TemplateData;
use Oro\Bundle\EntityBundle\Twig\Sandbox\VariableProcessorInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * Process owner entity variable for OAuth 2.0 client entity.
 */
class ClientEntityVariableProcessor implements VariableProcessorInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function process(string $variable, array $processorArguments, TemplateData $data): void
    {
        /** @var Client $entity */
        $entity = $data->getEntityVariable($data->getParentVariablePath($variable));
        $ownerEntityClass = $entity->getOwnerEntityClass();
        $ownerEntity = null;
        if ($processorArguments['related_entity_name'] === $ownerEntityClass) {
            $ownerEntity = $this->getOwnerEntity($ownerEntityClass, $entity->getOwnerEntityId());
        }

        $data->setComputedVariable($variable, $ownerEntity);
    }

    /**
     * @param string $ownerEntityClass
     * @param int    $ownerEntityId
     *
     * @return object|null
     */
    private function getOwnerEntity(string $ownerEntityClass, int $ownerEntityId)
    {
        return $this->doctrine->getManagerForClass($ownerEntityClass)
            ->find($ownerEntityClass, $ownerEntityId);
    }
}
