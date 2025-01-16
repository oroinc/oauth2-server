<?php

namespace Oro\Bundle\OAuth2ServerBundle\NotificationAlert;

use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertInterface;
use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertManager;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;

/**
 * Represents a notification alert item for OAuth2 private key.
 */
class OAuth2PrivateKeyNotificationAlert implements NotificationAlertInterface
{
    public const string SOURCE_TYPE = 'OAuth2Server';
    public const string RESOURCE_TYPE = 'oauth2_private_key';
    public const string ALERT_TYPE_PRIVATE_KEY_PERMISSIONS = 'file_permissions';

    protected string $id;
    protected string $alertType;
    protected string $message;
    protected ?int $organizationId = null;

    public static function createForOauthPrivateKey(
        string $message,
        ?int $organizationId = null
    ): OAuth2PrivateKeyNotificationAlert {
        $item = new self();
        $item->id = UUIDGenerator::v4();
        $item->alertType = self::ALERT_TYPE_PRIVATE_KEY_PERMISSIONS;
        $item->message = $message;
        $item->organizationId = $organizationId;

        return $item;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[\Override]
    public function getSourceType(): string
    {
        return self::SOURCE_TYPE;
    }

    #[\Override]
    public function toArray(): array
    {
        $data = [
            NotificationAlertManager::ID            => $this->id,
            NotificationAlertManager::SOURCE_TYPE   => self::SOURCE_TYPE,
            NotificationAlertManager::RESOURCE_TYPE => self::RESOURCE_TYPE,
            NotificationAlertManager::ALERT_TYPE    => $this->alertType,
            NotificationAlertManager::MESSAGE       => $this->message,
        ];

        if (null !== $this->organizationId) {
            $data[NotificationAlertManager::ORGANIZATION] = $this->organizationId;
        }

        return $data;
    }
}
