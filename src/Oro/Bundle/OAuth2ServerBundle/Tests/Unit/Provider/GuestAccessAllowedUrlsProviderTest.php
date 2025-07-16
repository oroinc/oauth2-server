<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\GuestAccessAllowedUrlsProvider;
use PHPUnit\Framework\TestCase;

class GuestAccessAllowedUrlsProviderTest extends TestCase
{
    public function testGetAllowedUrlsPatterns(): void
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
