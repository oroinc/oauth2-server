<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\Command\CommandTestingTrait;
use Oro\Component\Testing\ReflectionUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

class CheckKeysPermissionsCommandTest extends WebTestCase
{
    use CommandTestingTrait;

    private CryptKeyFile $oAuth2PrivateKey;

    private string $originalOAuth2PrivateKeyPath;

    protected function setUp(): void
    {
        $this->initClient();

        $this->command = $this->findCommand('oro:cron:oauth-server:check-keys-permissions');
        $this->commandTester = $this->getCommandTester($this->command);

        $this->oAuth2PrivateKey = self::getContainer()->get('oro_oauth2_server.league.private_key');
        $this->originalOAuth2PrivateKeyPath = $this->oAuth2PrivateKey->getKeyPath();
    }

    protected function tearDown(): void
    {
        $this->setOAuth2PrivateKeyPath($this->originalOAuth2PrivateKeyPath);
    }

    public function testExecuteWhenInvalidPermissions(): void
    {
        $this->commandTester->execute([]);

        $this->assertOutputContains($this->commandTester, 'Checking OAuth 2.0 private key permissions.');
        $this->assertOutputContains($this->commandTester, 'OAuth 2.0 private key permissions are not secure.');
    }

    public function testExecuteReturnsSuccess(): void
    {
        $cachePath = self::getContainer()->getParameter('kernel.cache_dir');
        $privateKeyPath = tempnam($cachePath, 'private.key');
        $filesystem = new Filesystem();
        $filesystem->dumpFile($privateKeyPath, 'sample_key');
        $filesystem->chmod($privateKeyPath, 0600);
        clearstatcache();

        $this->setOAuth2PrivateKeyPath($privateKeyPath);

        try {
            $this->commandTester->execute([]);
        } finally {
            $filesystem->remove($privateKeyPath);
        }

        self::assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $this->assertOutputContains($this->commandTester, 'Checking OAuth 2.0 private key permissions.');
        $this->assertOutputContains($this->commandTester, 'OAuth 2.0 private key permissions are secure.');
    }

    public function testExecuteReturnsFailure(): void
    {
        $cachePath = self::getContainer()->getParameter('kernel.cache_dir');
        $privateKeyPath = tempnam($cachePath, 'private.key');
        $filesystem = new Filesystem();
        $filesystem->dumpFile($privateKeyPath, 'sample_key');
        $filesystem->chmod($privateKeyPath, 0644);
        clearstatcache();

        $this->setOAuth2PrivateKeyPath($privateKeyPath);

        $this->commandTester->execute([]);

        $this->assertOutputContains($this->commandTester, 'Checking OAuth 2.0 private key permissions.');
        $this->assertOutputContains(
            $this->commandTester,
            'Be aware that the locally generated private key may have permissions that allow access by other '
            . 'Linux users. For production deployment, ensure that only the web server has read and write '
            . 'permissions for the private key. [ERROR] OAuth 2.0 private key permissions are not secure.'
        );
    }

    public function testExecuteReturnsNotExists(): void
    {
        $cachePath = self::getContainer()->getParameter('kernel.cache_dir');
        $privateKeyPath = $cachePath . DIRECTORY_SEPARATOR . uniqid('private_', true) . '.key';

        $this->setOAuth2PrivateKeyPath($privateKeyPath);

        $this->commandTester->execute([]);

        $this->assertOutputContains($this->commandTester, 'Checking OAuth 2.0 private key permissions.');
        $this->assertOutputContains($this->commandTester, 'OAuth 2.0 private key does not exist.');
    }

    private function setOAuth2PrivateKeyPath(string $path): void
    {
        ReflectionUtil::setPropertyValue($this->oAuth2PrivateKey, 'keyPath', $path);
    }
}
