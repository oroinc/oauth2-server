<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\ReflectionUtil;
use Oro\Component\Testing\TempDirExtension;
use Symfony\Component\Filesystem\Filesystem;

class CheckKeysPermissionsCommandTest extends WebTestCase
{
    use TempDirExtension;

    private CryptKeyFile $oAuth2PrivateKey;
    private string $originalOAuth2PrivateKeyPath;

    protected function setUp(): void
    {
        $this->initClient();

        $this->oAuth2PrivateKey = self::getContainer()->get('oro_oauth2_server.league.private_key');
        $this->originalOAuth2PrivateKeyPath = $this->oAuth2PrivateKey->getKeyPath();
    }

    protected function tearDown(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->originalOAuth2PrivateKeyPath);
    }

    private function setOAuth2KeyPath(CryptKeyFile $key, string $path): void
    {
        ReflectionUtil::setPropertyValue($key, 'keyPath', $path);
    }

    private function createOAuth2KeyFile(string $path, int $chmodMode): string
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($path, 'sample_key');
        $filesystem->chmod($path, $chmodMode);
        clearstatcache();

        return $path;
    }

    private function getRandomPath(): string
    {
        return $this->getTempFile('oauth_check_keys_permissions', 'key', '.key');
    }

    public function testExecuteWhenInvalidPermissions(): void
    {
        $result = self::runCommand('oro:cron:oauth-server:check-keys-permissions');

        self::assertStringContainsString('Checking OAuth 2.0 private key permissions.', $result);
        self::assertStringContainsString('OAuth 2.0 private key permissions are not secure.', $result);
    }

    public function testExecuteReturnsSuccess(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->createOAuth2KeyFile($this->getRandomPath(), 0600));

        $result = self::runCommand('oro:cron:oauth-server:check-keys-permissions');

        self::assertStringContainsString('Checking OAuth 2.0 private key permissions.', $result);
        self::assertStringContainsString('OAuth 2.0 private key permissions are secure.', $result);
    }

    public function testExecuteReturnsFailure(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->createOAuth2KeyFile($this->getRandomPath(), 0644));

        $result = self::runCommand('oro:cron:oauth-server:check-keys-permissions');

        self::assertStringContainsString('Checking OAuth 2.0 private key permissions.', $result);
        self::assertStringContainsString(
            'Be aware that the locally generated private key may have permissions that allow access by other '
            . 'Linux users. For production deployment, ensure that only the web server has read and write '
            . 'permissions for the private key. [WARNING] OAuth 2.0 private key permissions are not secure.',
            $result
        );
    }

    public function testExecuteReturnsNotExists(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->getRandomPath());

        $result = self::runCommand('oro:cron:oauth-server:check-keys-permissions');

        self::assertStringContainsString('Checking OAuth 2.0 private key permissions.', $result);
        self::assertStringContainsString('OAuth 2.0 private key does not exist.', $result);
    }
}
