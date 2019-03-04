<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Form\Type;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\FormBundle\Form\Extension\TooltipFormExtension;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\ClientType;
use Oro\Bundle\TranslationBundle\Translation\Translator;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Test\TypeTestCase;

class ClientTypeTest extends TypeTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getExtensions()
    {
        return [
            new PreloadedExtension(
                [new ClientType(['grant1', 'grant2'])],
                [
                    FormType::class => [
                        new TooltipFormExtension(
                            $this->createMock(ConfigProvider::class),
                            $this->createMock(Translator::class)
                        )
                    ]
                ]
            )
        ];
    }

    public function testSubmit()
    {
        $client = new Client();
        $submittedData = [
            'name'   => 'test name',
            'active' => 1,
            'grants' => ['grant2']
        ];

        $form = $this->factory->create(ClientType::class, $client);
        $form->submit($submittedData);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());

        self::assertEquals('test name', $client->getName());
        self::assertTrue($client->isActive());
        self::assertEquals(['grant2'], $client->getGrants());
    }
}
