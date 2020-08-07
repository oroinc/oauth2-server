<?php

namespace Oro\Bundle\OAuth2ServerBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\AccessTokenCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\AuthCodeCleaner;
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
 * * outdated OAuth 2.0 auth codes
 */
class CleanupCommand extends Command implements CronCommandInterface
{
    /** @var string */
    protected static $defaultName = 'oro:cron:oauth-server:cleanup';

    /** @var ClientCleaner */
    private $clientCleaner;

    /** @var AccessTokenCleaner */
    private $accessTokenCleaner;

    /** @var RefreshTokenCleaner */
    private $refreshTokenCleaner;

    /** @var AuthCodeCleaner */
    private $authCodeCleaner;

    /**
     * @param ClientCleaner       $clientCleaner
     * @param AccessTokenCleaner  $accessTokenCleaner
     * @param RefreshTokenCleaner $refreshTokenCleaner
     * @param AuthCodeCleaner     $authCodeCleaner
     */
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

        $io->comment('Removing outdated OAuth 2.0 auth codes...');
        $this->authCodeCleaner->cleanUp();

        $io->success('Completed');
    }
}
