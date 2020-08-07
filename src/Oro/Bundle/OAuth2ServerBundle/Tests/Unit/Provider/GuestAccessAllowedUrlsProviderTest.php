<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\GuestAccessAllowedUrlsProvider;

class GuestAccessAllowedUrlsProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetAllowedUrlsPatterns()
    {
        $provider = new GuestAccessAllowedUrlsProvider();

        self::assertEmpty($provider->getAllowedUrlsPatterns());
        $provider->addAllowedUrlPattern('test');
        self::assertEquals(['test'], $provider->getAllowedUrlsPatterns());
    }
}
