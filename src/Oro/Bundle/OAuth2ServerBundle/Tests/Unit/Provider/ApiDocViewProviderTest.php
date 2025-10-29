<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use PHPUnit\Framework\TestCase;

class ApiDocViewProviderTest extends TestCase
{
    private ApiDocViewProvider $apiDocViewProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiDocViewProvider = new ApiDocViewProvider([
            'frontend_api' => ['Frontend API', ['frontend', 'rest', 'json_api']],
            'frontend_api_other' => ['Frontend API (other)', ['frontend', 'rest', 'json_api', 'other']],
            'frontend_api_without_label' => [null, ['frontend', 'rest']],
            'backend_api' => ['Backend API', ['rest', 'json_api']],
            'backend_api_other' => ['Backend API (other)', ['rest', 'json_api', 'other']],
            'backend_api_without_label' => [null, ['rest']],
            'old_backend_api' => ['Old Backend API', null]
        ]);
    }

    /**
     * @dataProvider getViewsDataProvider
     */
    public function testGetViews(bool $isFrontend, array $views): void
    {
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
}
