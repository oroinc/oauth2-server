<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiDocViewProviderTest extends TestCase
{
    private const VIEWS = [
        'frontend_api' => ['Frontend API', ['frontend', 'rest', 'json_api']],
        'frontend_api_other' => ['Frontend API (other)', ['frontend', 'rest', 'json_api', 'other']],
        'frontend_api_without_label' => [null, ['frontend', 'rest']],
        'backend_api' => ['Backend API', ['rest', 'json_api']],
        'backend_api_other' => ['Backend API (other)', ['rest', 'json_api', 'other']],
        'backend_api_without_label' => [null, ['rest']],
        'old_backend_api' => ['Old Backend API', null]
    ];

    private TranslatorInterface&MockObject $translator;
    private ApiDocViewProvider $apiDocViewProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->apiDocViewProvider = new ApiDocViewProvider(self::VIEWS, $this->translator);
    }

    /**
     * @dataProvider getViewsDataProvider
     */
    public function testGetViews(bool $isFrontend, array $views): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnArgument(0);

        self::assertSame($views, $this->apiDocViewProvider->getViews($isFrontend));
    }

    /**
     * @dataProvider getViewsDataProvider
     */
    public function testGetViewsWhenHasTranslatedLabels(bool $isFrontend, array $views): void
    {
        foreach ($views as $name => $label) {
            if ($label) {
                $views[$name] = 'oro.api.open_api.views.' . $name . '.label (translated)';
            }
        }

        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(fn ($id) => $id . ' (translated)');

        self::assertSame($views, $this->apiDocViewProvider->getViews($isFrontend));
    }

    public static function getViewsDataProvider(): array
    {
        return [
            'frontend' => [
                true,
                [
                    'frontend_api' => 'Frontend API',
                    'frontend_api_other' => 'Frontend API (other)',
                    'frontend_api_without_label' => null
                ]
            ],
            'backend' => [
                false,
                [
                    'backend_api' => 'Backend API',
                    'backend_api_other' => 'Backend API (other)',
                    'backend_api_without_label' => null,
                    'old_backend_api' => 'Old Backend API'
                ]
            ]
        ];
    }

    /**
     * @dataProvider getViewsByRequestTypeDataProvider
     */
    public function testGetViewsByRequestType(RequestType $requestType, array $views): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnArgument(0);

        self::assertSame($views, $this->apiDocViewProvider->getViewsByRequestType($requestType));
    }

    /**
     * @dataProvider getViewsByRequestTypeDataProvider
     */
    public function testGetViewsByRequestTypeWhenHasTranslatedLabels(RequestType $requestType, array $views): void
    {
        foreach ($views as $name => $label) {
            if ($label) {
                $views[$name] = 'oro.api.open_api.views.' . $name . '.label (translated)';
            }
        }

        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(fn ($id) => $id . ' (translated)');

        self::assertSame($views, $this->apiDocViewProvider->getViewsByRequestType($requestType));
    }

    public static function getViewsByRequestTypeDataProvider(): array
    {
        return [
            'frontend (json_api, rest)' => [
                new RequestType(['frontend', 'json_api', 'rest']),
                [
                    'frontend_api' => 'Frontend API'
                ]
            ],
            'frontend (json_api, rest) - with custom aspect' => [
                new RequestType(['frontend', 'json_api', 'rest', 'custom']),
                [
                    'frontend_api' => 'Frontend API'
                ]
            ],
            'frontend (other, rest)' => [
                new RequestType(['frontend', 'other', 'rest', 'json_api']),
                [
                    'frontend_api_other' => 'Frontend API (other)'
                ]
            ],
            'frontend (other, rest) - with custom aspect' => [
                new RequestType(['frontend', 'other', 'rest', 'json_api', 'custom']),
                [
                    'frontend_api_other' => 'Frontend API (other)'
                ]
            ],
            'frontend (rest)' => [
                new RequestType(['frontend', 'rest']),
                [
                    'frontend_api_without_label' => null
                ]
            ],
            'frontend (rest) - with custom aspect' => [
                new RequestType(['frontend', 'rest', 'custom']),
                [
                    'frontend_api_without_label' => null
                ]
            ],
            'backend (json_api, rest)' => [
                new RequestType(['json_api', 'rest']),
                [
                    'backend_api' => 'Backend API'
                ]
            ],
            'backend (json_api, rest) - with custom aspect' => [
                new RequestType(['json_api', 'rest', 'custom']),
                [
                    'backend_api' => 'Backend API'
                ]
            ],
            'backend (other, rest)' => [
                new RequestType(['other', 'rest', 'json_api']),
                [
                    'backend_api_other' => 'Backend API (other)'
                ]
            ],
            'backend (other, rest) - with custom aspect' => [
                new RequestType(['other', 'rest', 'json_api', 'custom']),
                [
                    'backend_api_other' => 'Backend API (other)'
                ]
            ],
            'backend (rest)' => [
                new RequestType(['rest']),
                [
                    'backend_api_without_label' => null
                ]
            ],
            'backend (rest) - with custom aspect' => [
                new RequestType(['rest', 'custom']),
                [
                    'backend_api_without_label' => null
                ]
            ],
            'backend (old)' => [
                new RequestType([]),
                [
                    'old_backend_api' => 'Old Backend API'
                ]
            ],
            'unknown' => [
                new RequestType(['unknown']),
                []
            ]
        ];
    }

    /**
     * @dataProvider getViewLabelsDataProvider
     */
    public function testGetViewLabels(bool $isFrontend, array $viewNames, array $labels): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnArgument(0);

        self::assertSame($labels, $this->apiDocViewProvider->getViewLabels($isFrontend, $viewNames));
    }

    public static function getViewLabelsDataProvider(): array
    {
        return [
            'frontend' => [
                true,
                [
                    'frontend_api_without_label',
                    'frontend_api'
                ],
                [
                    'frontend_api' => 'Frontend API',
                    'frontend_api_without_label' => null
                ]
            ],
            'backend' => [
                false,
                [
                    'backend_api_without_label',
                    'old_backend_api',
                    'backend_api_other'
                ],
                [
                    'backend_api_other' => 'Backend API (other)',
                    'backend_api_without_label' => null,
                    'old_backend_api' => 'Old Backend API'
                ]
            ]
        ];
    }

    /**
     * @dataProvider getViewLabelsWhenHasTranslatedLabelsDataProvider
     */
    public function testGetViewLabelsWhenHasTranslatedLabels(bool $isFrontend, array $viewNames, array $labels): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(fn ($id) => $id . ' (translated)');

        self::assertSame($labels, $this->apiDocViewProvider->getViewLabels($isFrontend, $viewNames));
    }

    public static function getViewLabelsWhenHasTranslatedLabelsDataProvider(): array
    {
        return [
            'frontend' => [
                true,
                [
                    'frontend_api_without_label',
                    'frontend_api'
                ],
                [
                    'frontend_api' => 'oro.api.open_api.views.frontend_api.label (translated)',
                    'frontend_api_without_label' => null
                ]
            ],
            'backend' => [
                false,
                [
                    'backend_api_without_label',
                    'old_backend_api',
                    'backend_api_other'
                ],
                [
                    'backend_api_other' => 'oro.api.open_api.views.backend_api_other.label (translated)',
                    'backend_api_without_label' => null,
                    'old_backend_api' => 'oro.api.open_api.views.old_backend_api.label (translated)'
                ]
            ]
        ];
    }

    public function testGetViewDescription(): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->with('oro.api.open_api.views.backend_api.description')
            ->willReturn('Translated Description');

        self::assertSame('Translated Description', $this->apiDocViewProvider->getViewDescription('backend_api'));
    }

    public function testGetViewDescriptionWhenNoTranslatedDescription(): void
    {
        $this->translator->expects(self::any())
            ->method('trans')
            ->with('oro.api.open_api.views.backend_api.description')
            ->willReturnArgument(0);

        self::assertNull($this->apiDocViewProvider->getViewDescription('backend_api'));
    }
}
