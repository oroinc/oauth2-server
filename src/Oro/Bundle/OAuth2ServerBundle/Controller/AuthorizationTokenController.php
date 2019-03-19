<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Zend\Diactoros\Response;

/**
 * The controller that implement OAuth 2.0 authorization server entry point.
 */
class AuthorizationTokenController extends Controller
{
    /**
     * Gets OAuth 2.0 access token.
     *
     * @param ServerRequestInterface $serverRequest
     *
     * @return ResponseInterface
     */
    public function tokenAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $serverResponse = new Response();

        try {
            return $this->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (OAuthServerException $e) {
            $this->getLogger()->info($e->getMessage(), ['exception' => $e]);

            return $e->generateHttpResponse($serverResponse);
        }
    }

    /**
     * Handles OPTIONS HTTP method for OAuth 2.0 access token endpoint.
     *
     * @param SymfonyRequest $request
     *
     * @return SymfonyResponse
     */
    public function optionsAction(SymfonyRequest $request): SymfonyResponse
    {
        $response = new SymfonyResponse();
        $response->headers->set('Allow', 'OPTIONS, POST');
        if ($this->isCorsRequest($request)) {
            $origin = $request->headers->get('Origin');
            $allowedOrigins = $this->getParameter('oro_oauth2_server.cors.allow_origins');
            if (in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
            $requestMethod = $request->headers->get('Access-Control-Request-Method');
            if ($requestMethod) {
                $response->headers->set('Access-Control-Allow-Methods', $response->headers->get('Allow'));
                $response->headers->remove('Allow');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
                $preflightMaxAge = $this->getParameter('oro_oauth2_server.cors.preflight_max_age');
                if ($preflightMaxAge > 0) {
                    $response->headers->set('Access-Control-Max-Age', $preflightMaxAge);
                    // although OPTIONS requests are not cacheable, add "Cache-Control" header
                    // indicates that a caching is enabled to prevent making CORS preflight requests not cacheable
                    $response->headers->set('Cache-Control', sprintf('max-age=%d, public', $preflightMaxAge));
                    // the response depends on the Origin header value and should therefore not be served
                    // from cache for any other origin
                    $response->headers->set('Vary', 'Origin');
                }
            }
        }

        return $response;
    }

    /**
     * @param SymfonyRequest $request
     *
     * @return bool
     */
    private function isCorsRequest(SymfonyRequest $request): bool
    {
        return
            $request->headers->has('Origin')
            && $request->headers->get('Origin') !== $request->getSchemeAndHttpHost();
    }

    /**
     * @return AuthorizationServer
     */
    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->get('oro_oauth2_server.league.authorization_server');
        } catch (\LogicException $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            throw CryptKeyNotFoundException::create($e);
        }
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        return $this->get('logger');
    }
}
