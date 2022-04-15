<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\OauthLoginSourceProvider;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class OauthLoginSourceProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetLoginSourceForFailedRequestWithNonOAuthToken(): void
    {
        $token = new UsernamePasswordToken(new User(), 'test');
        $provider = new OauthLoginSourceProvider();
        self::assertNull($provider->getLoginSourceForFailedRequest($token, new \Exception()));
    }

    public function testGetLoginSourceForFailedRequestWithFailedUserOAuth2Token(): void
    {
        $token = new FailedUserOAuth2Token('test');
        $provider = new OauthLoginSourceProvider();
        self::assertEquals('OAuth', $provider->getLoginSourceForFailedRequest($token, new \Exception()));
    }

    public function testGetLoginSourceForFailedRequestWithOAuth2Token(): void
    {
        $token = new OAuth2Token();
        $provider = new OauthLoginSourceProvider();
        self::assertEquals('OAuth', $provider->getLoginSourceForFailedRequest($token, new \Exception()));
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndAuthFirewall(): void
    {
        $token = new UsernamePasswordToken(new User(), 'oauth2_authorization_authenticate');
        $provider = new OauthLoginSourceProvider();
        self::assertEquals('OAuthCode', $provider->getLoginSourceForFailedRequest($token, new \Exception()));
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndFrontendAuthFirewall(): void
    {
        $token = new UsernamePasswordToken(new User(), 'oauth2_frontend_authorization_authenticate');
        $provider = new OauthLoginSourceProvider();
        self::assertEquals('OAuthCode', $provider->getLoginSourceForFailedRequest($token, new \Exception()));
    }

    public function testGetLoginSourceForSuccessRequestWithNonOAuthToken(): void
    {
        $token = new UsernamePasswordToken(new User(), 'test');
        $provider = new OauthLoginSourceProvider();
        self::assertNull($provider->getLoginSourceForSuccessRequest($token));
    }

    public function testGetLoginSourceForSuccessRequestWithOAuth2Token(): void
    {
        $token = new OAuth2Token();
        $provider = new OauthLoginSourceProvider();
        self::assertEquals('OAuth', $provider->getLoginSourceForSuccessRequest($token));
    }
}
