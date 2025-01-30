<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\OauthLoginSourceProvider;
use Oro\Bundle\SecurityBundle\Authentication\Authenticator\UsernamePasswordOrganizationAuthenticator;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class OauthLoginSourceProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetLoginSourceForFailedRequestWithNonOAuthToken(): void
    {
        $provider = new OauthLoginSourceProvider();
        self::assertNull(
            $provider->getLoginSourceForFailedRequest(
                $this->getAuthenticatorMock('test'),
                new \Exception()
            )
        );
    }

    public function testGetLoginSourceForFailedRequestWithOAuth2Authenticator(): void
    {
        $provider = new OauthLoginSourceProvider();
        self::assertEquals(
            'OAuth',
            $provider->getLoginSourceForFailedRequest($this->createMock(OAuth2Authenticator::class), new \Exception())
        );
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndAuthFirewall(): void
    {
        $authenticator = $this->getAuthenticatorMock('test', 'oauth2_authorization_authenticate');
        $provider = new OauthLoginSourceProvider();
        self::assertEquals(
            'OAuthCode',
            $provider->getLoginSourceForFailedRequest($authenticator, new \Exception())
        );
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndFrontendAuthFirewall(): void
    {
        $authenticator = $this->getAuthenticatorMock(
            'test',
            'oauth2_frontend_authorization_authenticate'
        );
        $provider = new OauthLoginSourceProvider();
        self::assertEquals(
            'OAuthCode',
            $provider->getLoginSourceForFailedRequest($authenticator, new \Exception())
        );
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


    private function getAuthenticatorMock(string $userName, ?string $firewallName = null)
    {
        $passport = $this->createMock(Passport::class);
        $passport->expects($this->any())
            ->method('hasBadge')
            ->with(UserBadge::class)
            ->willReturn(true);
        $passport->expects($this->any())
            ->method('getBadge')
            ->with(UserBadge::class)
            ->willReturn(new UserBadge($userName));
        $authenticator = $this->createMock(UsernamePasswordOrganizationAuthenticator::class);

        if (null !== $firewallName) {
            $authenticator->expects($this->any())
                ->method('getFirewallName')
                ->willReturn($firewallName);
        }

        return $authenticator;
    }
}
