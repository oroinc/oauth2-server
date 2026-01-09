<?php

namespace Oro\Bundle\OAuth2ServerBundle\Command;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Generates OAuth2 private and public RSA keys.
 */
#[AsCommand(
    name: 'oro:oauth-server:generate-keys',
    description: 'Generates private and public RSA keys required for proper work of the OAuth2 server.'
)]
class GenerateKeysCommand extends Command
{
    private int $privateKeyPermission = 0644;

    public function __construct(
        private EncryptionKeysExistenceChecker $encryptionKeysExistenceChecker,
        private CryptKeyFile $privateKey,
        private CryptKeyFile $publicKey
    ) {
        parent::__construct();
    }

    /**
     * @param int $privateKeyPermission Permission mode (octal) to apply for the generated private key. Default is 0644.
     */
    public function setPrivateKeyPermission(int $privateKeyPermission): void
    {
        $this->privateKeyPermission = $privateKeyPermission;
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command generates private and public RSA keys required for proper work 
of the OAuth2 server.

  <info>php %command.full_name%</info>
HELP
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $privateKeyPath = $this->privateKey->getKeyPath();
        $publicKeyPath = $this->publicKey->getKeyPath();

        if (
            !$this->encryptionKeysExistenceChecker->isPrivateKeyExist() &&
            !$this->encryptionKeysExistenceChecker->isPublicKeyExist()
        ) {
            assert(extension_loaded('openssl'));

            $this->generatePrivateKey($privateKeyPath);
            $this->generatePublicKey($privateKeyPath, $publicKeyPath);

            $io->success(['The private and public keys are generated successfully:', $privateKeyPath, $publicKeyPath]);

            if (!$this->encryptionKeysExistenceChecker->isPrivateKeySecure()) {
                $io->warning(
                    <<<'WARNING'
                Be aware that the locally generated private key may have permissions that allow access by other
                Linux users. For production deployment, ensure that only the web server has read and write permissions
                for the private key.
            WARNING
                );
            }
        } else {
            $io->info(['The oAuth 2.0 private and public keys already exist:', $privateKeyPath, $publicKeyPath]);
        }

        return self::SUCCESS;
    }

    /**
     * openssl genrsa -out private.key 2048
     * openssl genrsa -aes128 -passout pass:_passphrase_ -out private.key 2048
     */
    protected function generatePrivateKey(string $privateKeyPath): void
    {
        $this->generateKey(['openssl', 'genrsa', '-out', $privateKeyPath, '2048']);

        $filesystem = new Filesystem();
        $filesystem->chmod($privateKeyPath, $this->privateKeyPermission);
    }

    /**
     * openssl rsa -in private.key -pubout -out public.key
     */
    protected function generatePublicKey(string $privateKeyPath, string $publicKeyPath): void
    {
        $this->generateKey(['openssl', 'rsa', '-in', $privateKeyPath, '-pubout', '-out', $publicKeyPath]);
    }

    private function generateKey(array $command): void
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }
}
