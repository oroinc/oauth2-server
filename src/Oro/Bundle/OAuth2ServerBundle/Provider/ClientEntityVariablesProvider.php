<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\EntityBundle\Twig\Sandbox\EntityVariablesProviderInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides definitions of variables for OAuth 2.0 client entity.
 */
class ClientEntityVariablesProvider implements EntityVariablesProviderInterface
{
    /** @var string[] */
    private $ownerEntityClasses;

    /** @var TranslatorInterface */
    private $translator;

    /** @var ConfigProvider */
    private $entityConfigProvider;

    /**
     * @param string[]            $ownerEntityClasses
     * @param TranslatorInterface $translator
     * @param ConfigProvider      $entityConfigProvider
     */
    public function __construct(
        array $ownerEntityClasses,
        TranslatorInterface $translator,
        ConfigProvider $entityConfigProvider
    ) {
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->translator = $translator;
        $this->entityConfigProvider = $entityConfigProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableDefinitions(): array
    {
        return [
            Client::class => $this->getClientEntityVariableDefinitions()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableGetters(): array
    {
        return [
            Client::class => $this->getClientEntityVariableGetters()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableProcessors(string $entityClass): array
    {
        if (Client::class === $entityClass) {
            return $this->getClientEntityVariableProcessors();
        }

        return [];
    }

    private function getClientEntityVariableDefinitions(): array
    {
        $result = [];
        foreach ($this->ownerEntityClasses as $associationName => $ownerEntityClass) {
            $result[$associationName] = [
                'type'                => RelationType::TO_ONE,
                'label'               => $this->translator->trans($this->getVariableLabel($ownerEntityClass)),
                'related_entity_name' => $ownerEntityClass
            ];
        }

        return $result;
    }

    private function getClientEntityVariableProcessors(): array
    {
        $result = [];
        foreach ($this->ownerEntityClasses as $associationName => $ownerEntityClass) {
            $result[$associationName] = [
                'processor'           => 'oauth2_client_entity',
                'related_entity_name' => $ownerEntityClass
            ];
        }

        return $result;
    }

    private function getClientEntityVariableGetters(): array
    {
        return [
            'scopes' => [
                'default_formatter' => 'oauth_scopes'
            ],
            'grants' => [
                'default_formatter' => [
                    'simple_array',
                    ['translatable' => true, 'translation_template' => 'oro.oauth2server.grant_types.%s']
                ]
            ]
        ];
    }

    private function getVariableLabel(string $ownerEntityClass): string
    {
        return (string) $this->entityConfigProvider->getConfig($ownerEntityClass)->get('label');
    }
}
