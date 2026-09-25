<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private const string DEFAULT_SESSION_TRANSFER_RETURN_URL = 'http://localhost:3000';
    private const string CUSTOM_SESSION_TRANSFER_RETURN_URL = 'https://storefront.example.com/';

    public function testSessionTransferReturnUrlDefaultValue(): void
    {
        $processedConfig = $this->processConfiguration([]);

        self::assertSame(
            self::DEFAULT_SESSION_TRANSFER_RETURN_URL,
            $processedConfig['session_transfer']['return_url']
        );
    }

    public function testSessionTransferReturnUrlCustomValue(): void
    {
        $processedConfig = $this->processConfiguration([
            ['session_transfer' => ['return_url' => self::CUSTOM_SESSION_TRANSFER_RETURN_URL]],
        ]);

        self::assertSame(
            self::CUSTOM_SESSION_TRANSFER_RETURN_URL,
            $processedConfig['session_transfer']['return_url']
        );
    }

    public function testSessionTransferReturnUrlWhenNotValidUrl(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The Session Transfer return URL "not a url" is not a valid absolute URL.');

        $this->processConfiguration([
            ['session_transfer' => ['return_url' => 'not a url']],
        ]);
    }

    public function testSessionTransferReturnUrlWhenEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'The path "oro_oauth2_server.session_transfer.return_url" cannot contain an empty value'
        );

        $this->processConfiguration([
            ['session_transfer' => ['return_url' => '']],
        ]);
    }

    private function processConfiguration(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
