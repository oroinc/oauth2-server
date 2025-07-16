<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EmailBundle\Event\EmailTemplateContextCollectEvent;
use Oro\Bundle\EmailBundle\Model\EmailTemplateCriteria;
use Oro\Bundle\EmailBundle\Model\From;
use Oro\Bundle\OAuth2ServerBundle\EventListener\EmailTemplateContextCollectWebsiteAwareEventListener;
use Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Stub\ClientStub;
use Oro\Bundle\UserBundle\Tests\Unit\Stub\UserStub;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use PHPUnit\Framework\TestCase;

class EmailTemplateContextCollectWebsiteAwareEventListenerTest extends TestCase
{
    private EmailTemplateContextCollectWebsiteAwareEventListener $listener;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->listener = new EmailTemplateContextCollectWebsiteAwareEventListener();
    }

    public function testShouldSkipWhenWebsiteAlreadySet(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $website = new Website();
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $templateParams = ['entity' => new UserStub(42)];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);
        $event->setTemplateContextParameter('website', $website);

        self::assertSame($website, $event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertSame($website, $event->getTemplateContextParameter('website'));
    }

    public function testShouldSkipWhenNoEntity(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $templateParams = ['sample_key' => 'sample_value'];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);

        self::assertNull($event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertNull($event->getTemplateContextParameter('website'));
    }

    public function testShouldSkipWhenEntityIsNotSupported(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $templateParams = ['entity' => new \stdClass()];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);

        self::assertNull($event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertNull($event->getTemplateContextParameter('website'));
    }

    public function testShouldSetWebsiteWhenHasCustomerUserWithWebsite(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $website = new Website();
        $customerUser = (new CustomerUser())
            ->setWebsite($website);
        $templateParams = ['entity' => (new ClientStub())->setCustomerUser($customerUser)];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);

        self::assertNull($event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertSame($website, $event->getTemplateContextParameter('website'));
    }

    public function testShouldSkipWhenHasCustomerUserWithoutWebsite(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $customerUser = new CustomerUser();
        $templateParams = ['entity' => (new ClientStub())->setCustomerUser($customerUser)];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);

        self::assertNull($event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertNull($event->getTemplateContextParameter('website'));
    }

    public function testShouldSkipWhenHasNoCustomerUser(): void
    {
        $from = From::emailAddress('no-reply@example.com');
        $recipients = [new UserStub(42)];
        $emailTemplateCriteria = new EmailTemplateCriteria('sample_template_name');
        $templateParams = ['entity' => new ClientStub()];

        $event = new EmailTemplateContextCollectEvent($from, $recipients, $emailTemplateCriteria, $templateParams);

        self::assertNull($event->getTemplateContextParameter('website'));

        $this->listener->onContextCollect($event);

        self::assertNull($event->getTemplateContextParameter('website'));
    }
}
