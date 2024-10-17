<?php

namespace Oro\Bundle\OAuth2ServerBundle\Generator;

/**
 * The generator that helps to generate codes for getting the oAuth2 token.
 */
class OAuth2CodeGenerator
{
    public const string HASH_ALGORITHM = 'sha256';
    public const string CODE_CHALLENGE_METHOD = 'S256';

    public static function generateCodeVerifier(): string
    {
        assert(extension_loaded('openssl'));

        $random = bin2hex(openssl_random_pseudo_bytes(32));

        return self::base64UrlEncode(pack('H*', $random));
    }

    public static function generateCodeChallenge(string $codeVerifier): string
    {
        return self::base64UrlEncode(pack('H*', hash(self::HASH_ALGORITHM, $codeVerifier)));
    }

    private static function base64UrlEncode(string $plainText): string
    {
        $base64 = trim(base64_encode($plainText), "=");

        return strtr($base64, '+/', '-_');
    }
}
