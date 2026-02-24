<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Twig;

use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Oro\Bundle\OAuth2ServerBundle\Twig\OroOauth2ServerExtension;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OroOauth2ServerExtensionTest extends TestCase
{
    use TwigExtensionTestCaseTrait;

    private ApiDocViewProvider&MockObject $apiDocViewProvider;
    private OroOauth2ServerExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiDocViewProvider = $this->createMock(ApiDocViewProvider::class);

        $this->extension = new OroOauth2ServerExtension($this->apiDocViewProvider);
    }

    /**
     * @dataProvider getApiViewLabelDataProvider
     */
    public function testGetApiViewLabel(bool $isFrontend, string $viewName, string $expectedLabel): void
    {
        $this->apiDocViewProvider->expects($this->once())
            ->method('getViews')
            ->with($isFrontend)
            ->willReturn([
                'api' => 'API',
                'api_without_label' => null
            ]);

        $this->assertEquals(
            $expectedLabel,
            self::callTwigFunction($this->extension, 'oro_oauth2_api_view_label', [$isFrontend, $viewName])
        );
    }

    public static function getApiViewLabelDataProvider(): array
    {
        return [
            'frontend' => [true, 'api', 'API'],
            'frontend (view without label)' => [true, 'api_without_label', ''],
            'frontend (unknown view)' => [true, 'unknown_api', ''],
            'backend' => [false, 'api', 'API'],
            'backend (view without label)' => [false, 'api_without_label', ''],
            'backend (unknown view)' => [false, 'unknown_api', '']
        ];
    }

    /**
     * @dataProvider getApiViewLabelsDataProvider
     */
    public function testGetApiViewLabels(
        bool $isFrontend,
        array $viewNames,
        array $labels,
        array $expectedLabels
    ): void {
        $this->apiDocViewProvider->expects($this->once())
            ->method('getViewLabels')
            ->with($isFrontend, $viewNames)
            ->willReturn($labels);

        $this->assertSame(
            $expectedLabels,
            self::callTwigFunction($this->extension, 'oro_oauth2_api_view_labels', [$isFrontend, $viewNames])
        );
    }

    public static function getApiViewLabelsDataProvider(): array
    {
        return [
            'frontend' => [
                true,
                ['frontend_api_without_label', 'frontend_api'],
                [
                    'frontend_api' => 'Frontend API',
                    'frontend_api_without_label' => null
                ],
                [
                    'Frontend API',
                    ''
                ]
            ],
            'backend' => [
                false,
                ['backend_api_without_label', 'old_backend_api', 'backend_api_other'],
                [
                    'backend_api_other' => 'Backend API (other)',
                    'backend_api_without_label' => null,
                    'old_backend_api' => 'Old Backend API'
                ],
                [
                    'Backend API (other)',
                    '',
                    'Old Backend API'
                ]
            ]
        ];
    }
}
