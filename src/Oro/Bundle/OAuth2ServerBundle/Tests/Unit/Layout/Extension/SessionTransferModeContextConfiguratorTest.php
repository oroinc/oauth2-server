<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Layout\Extension;

use Oro\Bundle\OAuth2ServerBundle\Layout\Extension\SessionTransferModeContextConfigurator;
use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferModeProvider;
use Oro\Component\Layout\LayoutContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SessionTransferModeContextConfiguratorTest extends TestCase
{
    private const string OPTION_NAME = 'is_in_session_transfer_mode';

    private SessionTransferModeProvider&MockObject $sessionTransferModeProvider;

    private SessionTransferModeContextConfigurator $configurator;

    #[\Override]
    protected function setUp(): void
    {
        $this->sessionTransferModeProvider = $this->createMock(SessionTransferModeProvider::class);
        $this->configurator = new SessionTransferModeContextConfigurator($this->sessionTransferModeProvider);
    }

    /**
     * @dataProvider configureContextProvider
     */
    public function testConfigureContext(bool $isInSessionTransferMode): void
    {
        $context = new LayoutContext();

        $this->sessionTransferModeProvider
            ->expects(self::once())
            ->method('isInSessionTransferMode')
            ->willReturn($isInSessionTransferMode);

        $this->configurator->configureContext($context);

        self::assertContains(self::OPTION_NAME, $context->getResolver()->getRequiredOptions());
        self::assertSame($isInSessionTransferMode, $context->get(self::OPTION_NAME));
    }

    public static function configureContextProvider(): iterable
    {
        yield 'in session transfer mode' => ['isInSessionTransferMode' => true];
        yield 'not in session transfer mode' => ['isInSessionTransferMode' => false];
    }
}
