<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\ConfigProviderMock;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientEntityVariablesProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class ClientEntityVariableProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var string[] */
    private $ownerEntityClasses;

    /** @var ConfigProviderMock */
    private $entityConfigProvider;

    /** @var ClientEntityVariablesProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->ownerEntityClasses = [
            'user'         => User::class,
            'organization' => Organization::class
        ];

        $this->entityConfigProvider = new ConfigProviderMock($this->createMock(ConfigManager::class), 'entity');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(function ($value) {
                return 'translated_' . $value;
            });

        $this->provider = new ClientEntityVariablesProvider(
            $this->ownerEntityClasses,
            $translator,
            $this->entityConfigProvider
        );
    }

    public function testGetVariableDefinitions()
    {
        $this->entityConfigProvider->addEntityConfig(User::class, ['label' => 'user_label']);
        $this->entityConfigProvider->addEntityConfig(Organization::class, ['label' => 'organization_label']);

        self::assertEquals(
            [
                Client::class => [
                    'user'         => [
                        'type'                => RelationType::TO_ONE,
                        'label'               => 'translated_user_label',
                        'related_entity_name' => User::class
                    ],
                    'organization' => [
                        'type'                => RelationType::TO_ONE,
                        'label'               => 'translated_organization_label',
                        'related_entity_name' => Organization::class
                    ]
                ]
            ],
            $this->provider->getVariableDefinitions()
        );
    }

    public function testGetVariableProcessorsForClientEntity()
    {
        self::assertEquals(
            [
                'user'         => [
                    'processor'           => 'oauth2_client_entity',
                    'related_entity_name' => User::class
                ],
                'organization' => [
                    'processor'           => 'oauth2_client_entity',
                    'related_entity_name' => Organization::class
                ]
            ],
            $this->provider->getVariableProcessors(Client::class)
        );
    }

    public function testGetVariableGetters()
    {
        self::assertEquals(
            [
                Client::class => [
                    'scopes' => [
                        'default_formatter' => 'oauth_scopes'
                    ],
                    'grants' => [
                        'default_formatter' => [
                            'simple_array',
                            ['translatable' => true, 'translation_template' => 'oro.oauth2server.grant_types.%s']
                        ]
                    ]
                ]
            ],
            $this->provider->getVariableGetters()
        );
    }

    public function testGetVariableProcessorsForAnotherEntity()
    {
        self::assertSame([], $this->provider->getVariableProcessors(\stdClass::class));
    }
}
