<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertManager;
use Oro\Bundle\OAuth2ServerBundle\NotificationAlert\OAuth2PrivateKeyNotificationAlert;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks if OAuth 2.0 private key permissions are secure, creates an alert notification if not.
 */
#[AsCommand(
    name: 'oro:cron:oauth-server:check-keys-permissions',
    description: 'Checks if OAuth 2.0 private key permissions are secure, creates an alert notification if not.'
)]
class CheckKeysPermissionsCommand extends Command implements CronCommandScheduleDefinitionInterface
{
    public function __construct(
        private EncryptionKeysExistenceChecker $checker,
        private NotificationAlertManager $notificationAlertManager,
        private ManagerRegistry $doctrine,
        private TranslatorInterface $translator
    ) {
        parent::__construct();
    }

    #[\Override]
    public function getDefaultDefinition(): string
    {
        return '0 0 * * *';
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command checks if OAuth 2.0 private key permissions are secure and 
creates an alert notification if private key can be accessed by someone except an owner.

  <info>php %command.full_name%</info>
HELP
            );
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Checking OAuth 2.0 private key permissions.');

        $isPrivateKeySecure = $this->checker->isPrivateKeySecure();

        if ($isPrivateKeySecure === null) {
            $io->error('OAuth 2.0 private key does not exist.');
        } elseif ($isPrivateKeySecure === true) {
            $io->success('OAuth 2.0 private key permissions are secure.');
        } else {
            $message = $this->translator->trans('oro.oauth2server.command.generate_keys.private_key_permission');

            $io->warning($message);

            $io->error('OAuth 2.0 private key permissions are not secure.');

            $this->sendNotificationAlert($message);
        }

        return Command::SUCCESS;
    }

    private function sendNotificationAlert(string $message): void
    {
        $organizations = $this->doctrine->getRepository(Organization::class)->findAll();

        foreach ($organizations as $organization) {
            $this->notificationAlertManager->addNotificationAlert(
                OAuth2PrivateKeyNotificationAlert::createForOauthPrivateKey($message, $organization->getId())
            );
        }
    }
}
