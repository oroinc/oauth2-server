<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferReturnUrlProvider;
use PHPUnit\Framework\TestCase;

final class SessionTransferReturnUrlProviderTest extends TestCase
{
    /**
     * @dataProvider getReturnUrlProvider
     */
    public function testGetReturnUrl(string $configuredReturnUrl, string $expectedReturnUrl): void
    {
        $provider = new SessionTransferReturnUrlProvider($configuredReturnUrl);

        self::assertSame($expectedReturnUrl, $provider->getReturnUrl());
    }

    public static function getReturnUrlProvider(): iterable
    {
        yield 'without trailing slash' => ['http://localhost:3000', 'http://localhost:3000'];
        yield 'with trailing slash' => ['https://storefront.example.com/', 'https://storefront.example.com'];
        yield 'with several trailing slashes' => ['https://storefront.example.com//', 'https://storefront.example.com'];
        yield 'with path and trailing slash' => [
            'https://storefront.example.com/shop/',
            'https://storefront.example.com/shop',
        ];
    }
}
