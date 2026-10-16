<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\Exception\InvalidLogoutRequestException;
use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\PostLogoutRedirectUriResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Ends the storefront session of the current customer user or the back-office session of the current user
 * and redirects to a registered post-logout redirect URI of an OAuth application.
 *
 * No #[Acl]: the action only ends the session of the caller.
 */
final class LogoutController extends AbstractController
{
    public function logoutAction(string $type, Request $request): Response
    {
        try {
            $redirectUri = $this->container->get(PostLogoutRedirectUriResolverInterface::class)
                ->resolve($request, 'frontend' === $type);
        } catch (InvalidLogoutRequestException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $response = new RedirectResponse($redirectUri);

        $security = $this->container->get(Security::class);
        if (null === $security->getUser()) {
            return $response;
        }

        $logoutResponse = $security->logout(false);
        if (null !== $logoutResponse) {
            $this->copyLogoutResponseHeaders($logoutResponse, $response);
        }

        return $response;
    }

    /**
     * Keeps the headers that the firewall logout listeners added (e.g. Clear-Site-Data and cookies),
     * except the redirect target.
     */
    private function copyLogoutResponseHeaders(Response $logoutResponse, Response $response): void
    {
        foreach ($logoutResponse->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            if (0 !== strcasecmp('Location', $name)) {
                $response->headers->set($name, $values);
            }
        }
        foreach ($logoutResponse->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                PostLogoutRedirectUriResolverInterface::class,
                Security::class
            ]
        );
    }
}
