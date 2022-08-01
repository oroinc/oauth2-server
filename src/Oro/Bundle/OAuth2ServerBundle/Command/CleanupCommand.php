<?php
declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\AccessTokenCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\AuthCodeCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\ClientCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\RefreshTokenCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes outdated OAuth tokens, codes and applications.
 *
 * The following data is removed:
 * * outdated OAuth 2.0 access tokens
 * * outdated OAuth 2.0 refresh tokens
 * * OAuth 2.0 clients that belong to removed users
 * * outdated OAuth 2.0 auth codes
 */
class CleanupCommand extends Command implements CronCommandScheduleDefinitionInterface
{
    /** @var string */
    protected static $defaultName = 'oro:cron:oauth-server:cleanup';

    private ClientCleaner $clientCleaner;
    private AccessTokenCleaner $accessTokenCleaner;
    private RefreshTokenCleaner $refreshTokenCleaner;
    private AuthCodeCleaner $authCodeCleaner;

    public function __construct(
        ClientCleaner $clientCleaner,
        AccessTokenCleaner $accessTokenCleaner,
        RefreshTokenCleaner $refreshTokenCleaner,
        AuthCodeCleaner $authCodeCleaner
    ) {
        parent::__construct();
        $this->clientCleaner = $clientCleaner;
        $this->accessTokenCleaner = $accessTokenCleaner;
        $this->refreshTokenCleaner = $refreshTokenCleaner;
        $this->authCodeCleaner = $authCodeCleaner;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultDefinition(): string
    {
        return '0 0 * * *';
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function configure()
    {
        $this
            ->setDescription('Removes outdated OAuth tokens, codes and applications.')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command removes outdated OAuth 2.0 access tokens, refresh tokens
and auth codes. It also removes OAuth 2.0 applications that belong to removed users.

  <info>php %command.full_name%</info>

HELP
            )
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Removing outdated OAuth 2.0 refresh tokens...');
        $this->refreshTokenCleaner->cleanUp();

        $io->comment('Removing outdated OAuth 2.0 access tokens...');
        $this->accessTokenCleaner->cleanUp();

        $io->comment('Removing OAuth 2.0 applications that belong to removed users...');
        $this->clientCleaner->cleanUp();

        $io->comment('Removing outdated OAuth 2.0 auth codes...');
        $this->authCodeCleaner->cleanUp();

        $io->success('Completed');

        return 0;
    }
}
