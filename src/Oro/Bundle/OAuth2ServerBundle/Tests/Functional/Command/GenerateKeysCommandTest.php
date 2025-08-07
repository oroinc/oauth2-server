<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\ReflectionUtil;
use Oro\Component\Testing\TempDirExtension;
use Symfony\Component\Filesystem\Filesystem;

class GenerateKeysCommandTest extends WebTestCase
{
    use TempDirExtension;

    private CryptKeyFile $oAuth2PrivateKey;
    private CryptKeyFile $oAuth2PublicKey;
    private string $initialOAuth2PrivateKeyPath;
    private string $initialOAuth2PublicKeyPath;

    protected function setUp(): void
    {
        $this->initClient();

        $this->oAuth2PrivateKey = self::getContainer()->get('oro_oauth2_server.league.private_key');
        $this->oAuth2PublicKey = self::getContainer()->get('oro_oauth2_server.league.public_key');
        $this->initialOAuth2PrivateKeyPath = $this->oAuth2PrivateKey->getKeyPath();
        $this->initialOAuth2PublicKeyPath = $this->oAuth2PublicKey->getKeyPath();
    }

    protected function tearDown(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->initialOAuth2PrivateKeyPath);
        $this->setOAuth2KeyPath($this->oAuth2PublicKey, $this->initialOAuth2PublicKeyPath);
    }

    private function setOAuth2KeyPath(CryptKeyFile $key, string $path): void
    {
        ReflectionUtil::setPropertyValue($key, 'keyPath', $path);
    }

    private function createOAuth2KeyFile(string $path): string
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($path, 'sample_key');
        clearstatcache();

        return $path;
    }

    private function getRandomPath(): string
    {
        return $this->getTempFile('oauth_generate_keys', 'key', '.key');
    }

    public function testExecuteReturnsSuccess(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->getRandomPath());
        $this->setOAuth2KeyPath($this->oAuth2PublicKey, $this->getRandomPath());

        $result = self::runCommand('oro:oauth-server:generate-keys');

        self::assertStringContainsString('The private and public keys are generated successfully', $result);
        self::assertStringContainsString(
            'Be aware that the locally generated private key may have permissions that allow access by'
            . ' other Linux users. For production deployment, ensure that only the web server has read and'
            . ' write permissions for the private key.',
            $result
        );
    }

    public function testExecuteReturnsFailureWhenAlreadyExists(): void
    {
        $this->setOAuth2KeyPath($this->oAuth2PrivateKey, $this->createOAuth2KeyFile($this->getRandomPath()));
        $this->setOAuth2KeyPath($this->oAuth2PublicKey, $this->createOAuth2KeyFile($this->getRandomPath()));

        $result = self::runCommand('oro:oauth-server:generate-keys');

        self::assertStringContainsString('The oAuth 2.0 private and public keys already exist', $result);
    }
}
