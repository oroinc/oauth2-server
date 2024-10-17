<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\NotificationAlert;

use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertManager;
use Oro\Bundle\OAuth2ServerBundle\NotificationAlert\OAuth2PrivateKeyNotificationAlert;
use PHPUnit\Framework\TestCase;

final class OAuth2PrivateKeyNotificationAlertTest extends TestCase
{
    public function testGetSourceType(): void
    {
        $alert = OAuth2PrivateKeyNotificationAlert::createForOauthPrivateKey('Wrong permission');
        self::assertEquals(OAuth2PrivateKeyNotificationAlert::SOURCE_TYPE, $alert->getSourceType());
    }

    public function testGetId(): void
    {
        $alert = OAuth2PrivateKeyNotificationAlert::createForOauthPrivateKey('Wrong permission');
        self::assertIsString($alert->getId());
    }

    public function testCreateForOauthPrivateKey(): void
    {
        $alert = OAuth2PrivateKeyNotificationAlert::createForOauthPrivateKey('Wrong permission', 1);

        $expected = [
            NotificationAlertManager::ID            => $alert->getId(),
            NotificationAlertManager::SOURCE_TYPE   => OAuth2PrivateKeyNotificationAlert::SOURCE_TYPE,
            NotificationAlertManager::RESOURCE_TYPE => OAuth2PrivateKeyNotificationAlert::RESOURCE_TYPE,
            NotificationAlertManager::MESSAGE       => 'Wrong permission',
            NotificationAlertManager::ORGANIZATION  => 1,
            NotificationAlertManager::ALERT_TYPE    =>
                OAuth2PrivateKeyNotificationAlert::ALERT_TYPE_PRIVATE_KEY_PERMISSIONS,
        ];

        self::assertInstanceOf(OAuth2PrivateKeyNotificationAlert::class, $alert);
        self::assertEquals($expected, $alert->toArray());
    }

    public function testCreateForOauthPrivateKeyWithoutOrganization(): void
    {
        $alert = OAuth2PrivateKeyNotificationAlert::createForOauthPrivateKey('Wrong permission');

        $expected = [
            NotificationAlertManager::ID            => $alert->getId(),
            NotificationAlertManager::SOURCE_TYPE   => OAuth2PrivateKeyNotificationAlert::SOURCE_TYPE,
            NotificationAlertManager::RESOURCE_TYPE => OAuth2PrivateKeyNotificationAlert::RESOURCE_TYPE,
            NotificationAlertManager::MESSAGE       => 'Wrong permission',
            NotificationAlertManager::ALERT_TYPE    =>
                OAuth2PrivateKeyNotificationAlert::ALERT_TYPE_PRIVATE_KEY_PERMISSIONS,
        ];

        self::assertInstanceOf(OAuth2PrivateKeyNotificationAlert::class, $alert);
        self::assertEquals($expected, $alert->toArray());
    }
}
