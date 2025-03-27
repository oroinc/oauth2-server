<?php

namespace Oro\Bundle\OAuth2ServerBundle\League;

/**
 * An utility class to encode and decode an user identifier string for auth code grant.
 */
class AuthCodeGrantUserIdentifierUtil
{
    private const string VISITOR_DELIMITER = '|visitor:';

    /**
     * Gets a string that represents an encoded user identifier for auth code grant.
     */
    public static function encodeIdentifier(string $userIdentifier, ?string $visitorSessionId = null): string
    {
        return $visitorSessionId
            ? $userIdentifier . self::VISITOR_DELIMITER . $visitorSessionId
            : $userIdentifier;
    }

    /**
     * Decodes auth code grant user identifier.
     *
     * @return array [user identifier, visitor session id]
     */
    public static function decodeIdentifier(string $identifier): array
    {
        $delimiterPos = strpos($identifier, self::VISITOR_DELIMITER);
        if (false === $delimiterPos) {
            return [$identifier, null];
        }

        return [
            substr($identifier, 0, $delimiterPos),
            substr($identifier, $delimiterPos + \strlen(self::VISITOR_DELIMITER))
        ];
    }
}
