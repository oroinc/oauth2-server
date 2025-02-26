<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\GuestAccessAllowedUrlsProvider;

class GuestAccessAllowedUrlsProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetAllowedUrlsPatterns()
    {
        if (!\class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }
        $provider = new GuestAccessAllowedUrlsProvider();

        self::assertEmpty($provider->getAllowedUrlsPatterns());
        $provider->addAllowedUrlPattern('test');
        self::assertEquals(['test'], $provider->getAllowedUrlsPatterns());
    }
}
