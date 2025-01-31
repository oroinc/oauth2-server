<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\GuestAccessAllowedUrlsProvider;

class GuestAccessAllowedUrlsProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetAllowedUrlsPatterns()
    {
        if (!\class_exists('Oro\Bundle\FrontendBundle\GuestAccess\Provider\GuestAccessAllowedUrlsProviderInterface')) {
            static::markTestSkipped('These tests can be run only if customer-portal package is present (BAP-22913).');
        }
        $provider = new GuestAccessAllowedUrlsProvider();

        self::assertEmpty($provider->getAllowedUrlsPatterns());
        $provider->addAllowedUrlPattern('test');
        self::assertEquals(['test'], $provider->getAllowedUrlsPatterns());
    }
}
