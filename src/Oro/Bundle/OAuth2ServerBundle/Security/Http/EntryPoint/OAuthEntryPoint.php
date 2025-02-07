<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentication entry point for OAuth API security firewalls.
 */
class OAuthEntryPoint implements AuthenticationEntryPointInterface
{
    #[\Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('', 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
