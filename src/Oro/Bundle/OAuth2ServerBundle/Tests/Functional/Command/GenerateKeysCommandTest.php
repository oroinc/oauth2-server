<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\ReflectionUtil;
use Symfony\Component\Filesystem\Filesystem;

class GenerateKeysCommandTest extends WebTestCase
{
    private CryptKeyFile $oAuth2PrivateKey;

    private CryptKeyFile $oAuth2PublicKey;

    private string $originalOAuth2PrivateKeyPath;

    private string $originalOAuth2PublicKeyPath;

    protected function setUp(): void
    {
        $this->initClient();

        $this->oAuth2PrivateKey = self::getContainer()->get('oro_oauth2_server.league.private_key');
        $this->oAuth2PublicKey = self::getContainer()->get('oro_oauth2_server.league.public_key');
        $this->originalOAuth2PrivateKeyPath = $this->oAuth2PrivateKey->getKeyPath();
        $this->originalOAuth2PublicKeyPath = $this->oAuth2PublicKey->getKeyPath();
    }

    protected function tearDown(): void
    {
        $this->setOAuth2PrivateKeyPath($this->originalOAuth2PrivateKeyPath);
        $this->setOAuth2PublicKeyPath($this->originalOAuth2PublicKeyPath);
    }

    public function testExecuteReturnsSuccess(): void
    {
        $privateKeyPath = $this->getRandomPath();
        $this->setOAuth2PrivateKeyPath($privateKeyPath);
        $publicKeyPath = $this->getRandomPath();
        $this->setOAuth2PublicKeyPath($publicKeyPath);

        try {
            $result = self::runCommand('oro:oauth-server:generate-keys');
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$privateKeyPath, $publicKeyPath]);
        }

        $this->assertStringContainsString('The private and public keys are generated successfully', $result);
        $this->assertStringContainsString(
            'Be aware that the locally generated private key may have permissions that allow access by'
            . ' other Linux users. For production deployment, ensure that only the web server has read and'
            . ' write permissions for the private key.',
            $result
        );
    }

    public function testExecuteReturnsFailureWhenAlreadyExists(): void
    {
        $privateKeyPath = $this->getRandomPath();
        $this->setOAuth2PrivateKeyPath($privateKeyPath);
        $publicKeyPath = $this->getRandomPath();
        $this->setOAuth2PublicKeyPath($publicKeyPath);

        $filesystem = new Filesystem();
        $filesystem->dumpFile($privateKeyPath, 'sample_key');
        $filesystem->dumpFile($publicKeyPath, 'sample_key');

        try {
            $result = self::runCommand('oro:oauth-server:generate-keys');
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$privateKeyPath, $publicKeyPath]);
        }

        $this->assertStringContainsString('The oAuth 2.0 private and public keys already exist', $result);
    }

    private function setOAuth2PrivateKeyPath(string $path): void
    {
        ReflectionUtil::setPropertyValue($this->oAuth2PrivateKey, 'keyPath', $path);
    }

    private function setOAuth2PublicKeyPath(string $path): void
    {
        ReflectionUtil::setPropertyValue($this->oAuth2PublicKey, 'keyPath', $path);
    }

    private function getRandomPath(): string
    {
        $cachePath = self::getContainer()->getParameter('kernel.cache_dir');

        return $cachePath . DIRECTORY_SEPARATOR . uniqid('key_', true) . '.key';
    }
}
