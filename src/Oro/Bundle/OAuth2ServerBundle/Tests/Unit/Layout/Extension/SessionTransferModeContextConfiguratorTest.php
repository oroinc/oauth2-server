<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Layout\Extension;

use Oro\Bundle\OAuth2ServerBundle\Layout\Extension\SessionTransferModeContextConfigurator;
use Oro\Bundle\OAuth2ServerBundle\Provider\SessionTransferModeProvider;
use Oro\Component\Layout\LayoutContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionTransferModeContextConfiguratorTest extends TestCase
{
    private SessionTransferModeProvider&MockObject $sessionTransferModeProvider;

    private SessionTransferModeContextConfigurator $configurator;

    #[\Override]
    protected function setUp(): void
    {
        $this->sessionTransferModeProvider = $this->createMock(SessionTransferModeProvider::class);
        $this->configurator = new SessionTransferModeContextConfigurator($this->sessionTransferModeProvider);
    }

    /**
     * @dataProvider configureContextDataProvider
     */
    public function testConfigureContext(bool $isInSessionTransferMode): void
    {
        $context = new LayoutContext();

        $this->sessionTransferModeProvider->expects(self::once())
            ->method('isInSessionTransferMode')
            ->willReturn($isInSessionTransferMode);

        $this->configurator->configureContext($context);

        self::assertContains(
            'is_in_session_transfer_mode',
            $context->getResolver()->getRequiredOptions()
        );
        self::assertSame($isInSessionTransferMode, $context->get('is_in_session_transfer_mode'));
    }

    public function configureContextDataProvider(): array
    {
        return [
            'In Session Transfer Mode' => ['isInSessionTransferMode' => true],
            'Not in Session Transfer Mode' => ['isInSessionTransferMode' => false],
        ];
    }
}
