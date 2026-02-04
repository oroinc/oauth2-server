<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthLoginSourceProvider;
use Oro\Bundle\SecurityBundle\Authentication\Authenticator\UsernamePasswordOrganizationAuthenticator;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class OAuthLoginSourceProviderTest extends TestCase
{
    private RequestStack $requestStack;
    private OAuthLoginSourceProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();

        $this->provider = new OAuthLoginSourceProvider($this->requestStack);
    }

    private function getAuthenticator(): AuthenticatorInterface
    {
        $passport = $this->createMock(Passport::class);
        $passport->expects(self::any())
            ->method('hasBadge')
            ->with(UserBadge::class)
            ->willReturn(true);
        $passport->expects(self::any())
            ->method('getBadge')
            ->with(UserBadge::class)
            ->willReturn(new UserBadge('test'));

        return $this->createMock(UsernamePasswordOrganizationAuthenticator::class);
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthToken(): void
    {
        self::assertNull(
            $this->provider->getLoginSourceForFailedRequest($this->getAuthenticator(), new \Exception())
        );
    }

    public function testGetLoginSourceForFailedRequestWithOAuth2Authenticator(): void
    {
        self::assertEquals(
            'OAuth',
            $this->provider->getLoginSourceForFailedRequest(
                $this->createMock(OAuth2Authenticator::class),
                new \Exception()
            )
        );
    }

    public function testGetLoginSourceForFailedRequestWithNonOAuthTokenAndAuthRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_oauth_login', true);
        $this->requestStack->push($request);
        self::assertEquals(
            'OAuthCode',
            $this->provider->getLoginSourceForFailedRequest($this->getAuthenticator(), new \Exception())
        );
    }

    public function testGetLoginSourceForSuccessRequestWithNonOAuthToken(): void
    {
        self::assertNull(
            $this->provider->getLoginSourceForSuccessRequest(new UsernamePasswordToken(new User(), 'test'))
        );
    }

    public function testGetLoginSourceForSuccessRequestWithOAuth2Token(): void
    {
        self::assertEquals('OAuth', $this->provider->getLoginSourceForSuccessRequest(new OAuth2Token()));
    }
}
