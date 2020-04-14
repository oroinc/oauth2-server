<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Formatter;

use Oro\Bundle\OAuth2ServerBundle\Formatter\ScopesFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScopesFormatterTest extends \PHPUnit\Framework\TestCase
{
    /** @var ScopesFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(function ($value) {
                return 'translated_' . $value;
            });

        $this->formatter = new ScopesFormatter($translator);
    }

    public function testFormatForEmptyValue()
    {
        self::assertEquals(
            'translated_oro.oauth2server.scopes.all',
            $this->formatter->format([])
        );
    }

    public function testFormat()
    {
        self::assertEquals(
            'val1, val2',
            $this->formatter->format(['val1', 'val2'])
        );
    }

    public function testGetDefaultValue()
    {
        self::assertEquals(
            'translated_oro.oauth2server.scopes.all',
            $this->formatter->getDefaultValue()
        );
    }
}
