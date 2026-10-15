<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Handler\Logout\Exception;

/**
 * Thrown when a logout request of an OAuth application cannot be accepted.
 */
class InvalidLogoutRequestException extends \RuntimeException
{
    public const int UNKNOWN_CLIENT = 1;
    public const int CLIENT_TYPE_MISMATCH = 2;
    public const int LOGOUT_NOT_ALLOWED = 3;
    public const int AUTHORIZATION_CODE_GRANT_NOT_SUPPORTED = 4;
    public const int UNREGISTERED_POST_LOGOUT_REDIRECT_URI = 5;

    private const string CLIENT_DOES_NOT_EXIST_MESSAGE = 'The OAuth application does not exist.';
    private const string LOGOUT_NOT_ALLOWED_MESSAGE = 'The OAuth application does not allow logout.';
    private const string UNREGISTERED_POST_LOGOUT_REDIRECT_URI_MESSAGE =
        'The post-logout redirect URI is not registered for the OAuth application.';

    public static function unknownClient(): self
    {
        return new self(self::CLIENT_DOES_NOT_EXIST_MESSAGE, self::UNKNOWN_CLIENT);
    }

    /**
     * The message does not reveal that the OAuth application exists for the other application type.
     */
    public static function clientTypeMismatch(): self
    {
        return new self(self::CLIENT_DOES_NOT_EXIST_MESSAGE, self::CLIENT_TYPE_MISMATCH);
    }

    public static function logoutNotAllowed(): self
    {
        return new self(self::LOGOUT_NOT_ALLOWED_MESSAGE, self::LOGOUT_NOT_ALLOWED);
    }

    public static function authorizationCodeGrantNotSupported(): self
    {
        return new self(self::LOGOUT_NOT_ALLOWED_MESSAGE, self::AUTHORIZATION_CODE_GRANT_NOT_SUPPORTED);
    }

    public static function unregisteredPostLogoutRedirectUri(): self
    {
        return new self(
            self::UNREGISTERED_POST_LOGOUT_REDIRECT_URI_MESSAGE,
            self::UNREGISTERED_POST_LOGOUT_REDIRECT_URI
        );
    }
}
