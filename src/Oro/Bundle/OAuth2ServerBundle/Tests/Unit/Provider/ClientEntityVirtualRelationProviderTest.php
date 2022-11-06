<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\ConfigProviderMock;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientEntityVirtualRelationProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class ClientEntityVirtualRelationProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var string[] */
    private $ownerEntityClasses;

    /** @var DoctrineHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrineHelper;

    /** @var ConfigProvider */
    private $entityConfigProvider;

    /** @var ClientEntityVirtualRelationProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->ownerEntityClasses = [
            'user'         => User::class,
            'organization' => Organization::class
        ];

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->entityConfigProvider = new ConfigProviderMock($this->createMock(ConfigManager::class), 'entity');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(function ($value) {
                return 'translated_' . $value;
            });

        $this->provider = new ClientEntityVirtualRelationProvider(
            $this->ownerEntityClasses,
            $this->doctrineHelper,
            $translator,
            $this->entityConfigProvider
        );
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testIsVirtualRelation(string $className, string $fieldName, bool $supported)
    {
        $this->assertEquals($supported, $this->provider->isVirtualRelation($className, $fieldName));
    }

    public function testGetTargetJoinAlias()
    {
        foreach (array_keys($this->ownerEntityClasses) as $relationName) {
            $this->assertEquals(
                $relationName,
                $this->provider->getTargetJoinAlias(Client::class, $relationName)
            );
        }
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testGetVirtualRelationQuery(string $className, string $fieldName, bool $expected)
    {
        $this->entityConfigProvider->addEntityConfig(User::class, ['label' => 'user_label']);
        $this->entityConfigProvider->addEntityConfig(Organization::class, ['label' => 'organization_label']);

        $result = $this->provider->getVirtualRelationQuery($className, $fieldName);
        if ($expected) {
            $this->assertNotEmpty($result);
        } else {
            $this->assertEmpty($result);
        }
    }

    public function testGetVirtualRelations()
    {
        $this->entityConfigProvider->addEntityConfig(User::class, ['label' => 'user_label']);
        $this->entityConfigProvider->addEntityConfig(Organization::class, ['label' => 'organization_label']);

        $this->doctrineHelper->expects($this->exactly(2))
            ->method('getSingleEntityIdentifierFieldName')
            ->willReturnMap([
                [User::class, true, 'id'],
                [Organization::class, true, 'id']
            ]);

        self::assertEquals(
            [
                'user'         => [
                    'label'               => 'translated_user_label',
                    'relation_type'       => RelationType::MANY_TO_ONE,
                    'related_entity_name' => User::class,
                    'target_join_alias'   => 'user',
                    'query'               => [
                        'join' => [
                            'left' => [
                                [
                                    'join'          => User::class,
                                    'alias'         => 'user',
                                    'conditionType' => 'WITH',
                                    'condition'     => sprintf(
                                        'entity.ownerEntityClass = \'%s\' AND entity.ownerEntityId = user.id',
                                        User::class
                                    )
                                ]
                            ]
                        ]
                    ]
                ],
                'organization' => [
                    'label'               => 'translated_organization_label',
                    'relation_type'       => RelationType::MANY_TO_ONE,
                    'related_entity_name' => Organization::class,
                    'target_join_alias'   => 'organization',
                    'query'               => [
                        'join' => [
                            'left' => [
                                [
                                    'join'          => Organization::class,
                                    'alias'         => 'organization',
                                    'conditionType' => 'WITH',
                                    'condition'     => sprintf(
                                        'entity.ownerEntityClass = \'%s\' AND entity.ownerEntityId = organization.id',
                                        Organization::class
                                    )
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            $this->provider->getVirtualRelations(Client::class)
        );
    }

    public function fieldDataProvider(): array
    {
        return [
            'incorrect class, incorrect field' => ['stdClass', 'test', false],
            'incorrect class, correct field'   => ['stdClass', 'user', false],
            'incorrect field'                  => [Client::class, 'test', false],
            'correct field'                    => [Client::class, 'user', true]
        ];
    }
}
