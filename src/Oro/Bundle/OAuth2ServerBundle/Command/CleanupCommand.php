<?php

namespace Oro\Bundle\OAuth2ServerBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\AccessTokenCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\ClientCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\RefreshTokenCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The command that removes the following data:
 * * outdated OAuth 2.0 access tokens
 * * outdated OAuth 2.0 refresh tokens
 * * OAuth 2.0 clients that belong to removed users
 */
class CleanupCommand extends Command implements CronCommandInterface
{
    /** @var ClientCleaner */
    private $clientCleaner;

    /** @var AccessTokenCleaner */
    private $accessTokenCleaner;

    /** @var RefreshTokenCleaner */
    private $refreshTokenCleaner;

    /**
     * @param ClientCleaner       $clientCleaner
     * @param AccessTokenCleaner  $accessTokenCleaner
     * @param RefreshTokenCleaner $refreshTokenCleaner
     */
    public function __construct(
        ClientCleaner $clientCleaner,
        AccessTokenCleaner $accessTokenCleaner,
        RefreshTokenCleaner $refreshTokenCleaner
    ) {
        parent::__construct();
        $this->clientCleaner = $clientCleaner;
        $this->accessTokenCleaner = $accessTokenCleaner;
        $this->refreshTokenCleaner = $refreshTokenCleaner;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultDefinition()
    {
        return '0 0 * * *';
    }

    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('oro:cron:oauth-server:cleanup')
            ->setDescription(
                'Removes outdated OAuth 2.0 access tokens, refresh tokens'
                . ' and OAuth 2.0 applications that belong to removed users'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Removing outdated OAuth 2.0 refresh tokens...');
        $this->refreshTokenCleaner->cleanUp();

        $io->comment('Removing outdated OAuth 2.0 access tokens...');
        $this->accessTokenCleaner->cleanUp();

        $io->comment('Removing OAuth 2.0 applications that belong to removed users...');
        $this->clientCleaner->cleanUp();

        $io->success('Completed');
    }
}
