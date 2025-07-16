<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\OauthLoginSourceProvider;
use Oro\Bundle\SecurityBundle\Authentication\Authenticator\UsernamePasswordOrganizationAuthenticator;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class OauthLoginSourceProviderTest extends TestCase
{
    private RequestStack $requestStack;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthToken(): void
    {
        $provider = new OauthLoginSourceProvider($this->requestStack);
        self::assertNull(
            $provider->getLoginSourceForFailedRequest(
                $this->getAuthenticatorMock('test'),
                new \Exception()
            )
        );
    }

    public function testGetLoginSourceForFailedRequestWithOAuth2Authenticator(): void
    {
        $provider = new OauthLoginSourceProvider($this->requestStack);
        self::assertEquals(
            'OAuth',
            $provider->getLoginSourceForFailedRequest($this->createMock(OAuth2Authenticator::class), new \Exception())
        );
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndAuthRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_oauth_login', true);
        $this->requestStack->push($request);
        $authenticator = $this->getAuthenticatorMock('test');
        $provider = new OauthLoginSourceProvider($this->requestStack);
        self::assertEquals(
            'OAuthCode',
            $provider->getLoginSourceForFailedRequest($authenticator, new \Exception())
        );
    }

    public function testGetLoginSourceForSuccessRequestWithNonOAuthToken(): void
    {
        $token = new UsernamePasswordToken(new User(), 'test');
        $provider = new OauthLoginSourceProvider($this->requestStack);
        self::assertNull($provider->getLoginSourceForSuccessRequest($token));
    }

    public function testGetLoginSourceForSuccessRequestWithOAuth2Token(): void
    {
        $token = new OAuth2Token();
        $provider = new OauthLoginSourceProvider($this->requestStack);
        self::assertEquals('OAuth', $provider->getLoginSourceForSuccessRequest($token));
    }


    private function getAuthenticatorMock(string $userName): UsernamePasswordOrganizationAuthenticator|MockObject
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

        return $authenticator;
    }
}
