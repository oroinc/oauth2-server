<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Model;

use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;
use PHPUnit\Framework\TestCase;

class SessionTransferTargetTest extends TestCase
{
    public function testGetters(): void
    {
        $target = new SessionTransferTarget(
            'target_route',
            ['id' => 10],
            ['website_id' => 20]
        );

        self::assertEquals('target_route', $target->getRoute());
        self::assertEquals(['id' => 10], $target->getRouteParameters());
        self::assertEquals(['website_id' => 20], $target->getContextData());
    }

    public function testContextDataDefaultsToEmptyArray(): void
    {
        $target = new SessionTransferTarget('target_route', []);

        self::assertEquals([], $target->getContextData());
    }
}
