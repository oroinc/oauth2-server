<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Controller;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadBackofficeLogoutClients;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendLogoutClients;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @dbIsolationPerTest
 */
final class BackofficeLogoutControllerTest extends WebTestCase
{
    private const string FIREWALL_EVENT_DISPATCHER = 'security.event_dispatcher.main';
    private const string LOGOUT_LISTENER_REDIRECT_URI = 'http://logout-listener.test/logged-out';
    private const string LOGOUT_LISTENER_COOKIE = 'logout_listener_cookie';
    private const string CLEAR_SITE_DATA_HEADER = 'Clear-Site-Data';
    private const string CLEAR_SITE_DATA_VALUE = '"cache", "cookies", "storage"';

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $this->loadFixtures([
            LoadBackofficeLogoutClients::class,
            LoadFrontendLogoutClients::class,
        ]);
    }

    private function requestLogout(array $parameters): Response
    {
        $this->client->request('GET', $this->getUrl('oro_oauth2_server_logout', $parameters));

        return $this->client->getResponse();
    }

    /**
     * @dataProvider serverParametersProvider
     */
    public function testLogoutEndsSessionAndRedirectsToRegisteredPostLogoutUri(array $server): void
    {
        $this->initClient([], $server);

        $response = $this->requestLogout([
            'client_id' => LoadBackofficeLogoutClients::LOGOUT_ALLOWED_CLIENT_ID,
            'post_logout_redirect_uri' => LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI,
        ]);

        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        self::assertSame(LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI, $response->headers->get('Location'));
        self::assertNull(self::getContainer()->get('security.token_storage')->getToken());
    }

    public static function serverParametersProvider(): iterable
    {
        yield 'user' => [self::generateBasicAuthHeader()];
        yield 'not logged in' => [[]];
    }

    public function testLogoutKeepsHeadersAddedByFirewallLogoutListenersExceptLocation(): void
    {
        $loggedOutUser = null;
        $logoutListener = static function (LogoutEvent $event) use (&$loggedOutUser): void {
            $loggedOutUser = $event->getToken()?->getUser();
            $logoutResponse = new RedirectResponse(
                self::LOGOUT_LISTENER_REDIRECT_URI,
                Response::HTTP_FOUND,
                [self::CLEAR_SITE_DATA_HEADER => self::CLEAR_SITE_DATA_VALUE]
            );
            $logoutResponse->headers->clearCookie(self::LOGOUT_LISTENER_COOKIE);
            $event->setResponse($logoutResponse);
        };
        $dispatcher = self::getContainer()->get(self::FIREWALL_EVENT_DISPATCHER);
        $dispatcher->addListener(LogoutEvent::class, $logoutListener);
        try {
            $response = $this->requestLogout([
                'client_id' => LoadBackofficeLogoutClients::LOGOUT_ALLOWED_CLIENT_ID,
                'post_logout_redirect_uri' => LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI,
            ]);
        } finally {
            $dispatcher->removeListener(LogoutEvent::class, $logoutListener);
        }

        self::assertInstanceOf(User::class, $loggedOutUser);
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        self::assertSame(LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI, $response->headers->get('Location'));
        self::assertSame(self::CLEAR_SITE_DATA_VALUE, $response->headers->get(self::CLEAR_SITE_DATA_HEADER));
        self::assertTrue(
            $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][self::LOGOUT_LISTENER_COOKIE]
                ->isCleared()
        );
    }

    /**
     * @dataProvider invalidLogoutRequestProvider
     */
    public function testLogoutIsRejectedWithoutEndingSession(array $parameters): void
    {
        $response = $this->requestLogout($parameters);

        self::assertResponseStatusCodeEquals($response, Response::HTTP_BAD_REQUEST);
        self::assertInstanceOf(
            User::class,
            self::getContainer()->get('security.token_storage')->getToken()?->getUser()
        );
    }

    public static function invalidLogoutRequestProvider(): iterable
    {
        yield 'unknown application' => [[
            'client_id' => 'unknown',
            'post_logout_redirect_uri' => LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI,
        ]];
        yield 'storefront application' => [[
            'client_id' => LoadFrontendLogoutClients::LOGOUT_ALLOWED_CLIENT_ID,
            'post_logout_redirect_uri' => LoadFrontendLogoutClients::POST_LOGOUT_REDIRECT_URI,
        ]];
        yield 'application that does not allow logout' => [[
            'client_id' => LoadBackofficeLogoutClients::LOGOUT_NOT_ALLOWED_CLIENT_ID,
            'post_logout_redirect_uri' => LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI,
        ]];
        yield 'unregistered post-logout redirect uri' => [[
            'client_id' => LoadBackofficeLogoutClients::LOGOUT_ALLOWED_CLIENT_ID,
            'post_logout_redirect_uri' => LoadBackofficeLogoutClients::POST_LOGOUT_REDIRECT_URI . '/account',
        ]];
    }
}
