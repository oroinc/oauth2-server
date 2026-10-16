<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Handler\Logout;

use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\Exception\InvalidLogoutRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Represents a service that decides whether a logout request of an OAuth application is accepted
 * and returns the URI to redirect the user to after the logout.
 */
interface PostLogoutRedirectUriResolverInterface
{
    /**
     * Returns the approved post-logout redirect URI.
     * The $frontend flag tells whether the request came to the storefront or to the back-office logout endpoint.
     *
     * @throws InvalidLogoutRequestException if the logout request is not accepted
     */
    public function resolve(Request $request, bool $frontend): string;
}
