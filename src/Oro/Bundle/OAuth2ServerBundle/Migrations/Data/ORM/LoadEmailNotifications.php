<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\NotificationBundle\Entity\EmailNotification;
use Oro\Bundle\NotificationBundle\Entity\RecipientList;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * Loads email notification events for OAuth 2.0 client entity.
 */
class LoadEmailNotifications extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [LoadEmailTemplates::class];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $this->createEmailNotification($manager, 'persist', 'user_oauth_application_created', 'user');
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $this->createEmailNotification(
                $manager,
                'persist',
                'customer_user_oauth_application_created',
                'customerUser'
            );
        }

        $manager->flush();
    }

    private function createEmailNotification(
        ObjectManager $manager,
        string $eventName,
        string $templateName,
        string $recipientAssociation
    ): EmailNotification {
        $emailNotification = new EmailNotification();
        $emailNotification->setEntityName(Client::class);
        $emailNotification->setEventName($this->getNotificationEvent($eventName));
        $emailNotification->setTemplate($this->getEmailTemplate($manager, $templateName));

        $recipientList = new RecipientList();
        $recipientList->setAdditionalEmailAssociations([$recipientAssociation]);
        $emailNotification->setRecipientList($recipientList);

        $manager->persist($recipientList);
        $manager->persist($emailNotification);

        return $emailNotification;
    }

    private function getNotificationEvent(string $name): string
    {
        return 'oro.notification.event.entity_post_' . $name;
    }

    private function getEmailTemplate(ObjectManager $manager, string $name): EmailTemplate
    {
        return $manager->getRepository(EmailTemplate::class)
            ->findOneBy(['name' => $name]);
    }
}
