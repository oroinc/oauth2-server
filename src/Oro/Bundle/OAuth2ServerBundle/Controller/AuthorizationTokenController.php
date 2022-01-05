<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\ExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\SuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The controller that implement OAuth 2.0 authorization server entry point.
 */
class AuthorizationTokenController extends AbstractController
{
    /**
     * Gets OAuth 2.0 access token.
     */
    public function tokenAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $serverResponse = new Response();

        try {
            $response = $this->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
            $this->get(SuccessHandler::class)->handle($serverRequest);
        } catch (OAuthServerException $e) {
            $this->get(ExceptionHandler::class)->handle($serverRequest, $e);
            $response = $e->generateHttpResponse($serverResponse);
        }

        if ($this->isCorsTokenRequest($serverRequest)) {
            $origin = $this->getTokenRequestOrigin($serverRequest);
            if ($this->isAllowedOrigin($origin)) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            }
        }

        return $response;
    }

    /**
     * Handles OPTIONS HTTP method for OAuth 2.0 access token endpoint.
     */
    public function optionsAction(SymfonyRequest $request): SymfonyResponse
    {
        $response = new SymfonyResponse();
        $response->headers->set('Allow', 'OPTIONS, POST');
        if ($this->isCorsRequest($request)) {
            $origin = $request->headers->get('Origin');
            if ($this->isAllowedOrigin($origin)) {
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

    private function isCorsRequest(SymfonyRequest $request): bool
    {
        return
            $request->headers->has('Origin')
            && $request->headers->get('Origin') !== $request->getSchemeAndHttpHost();
    }

    private function isCorsTokenRequest(ServerRequestInterface $request): bool
    {
        return
            $request->hasHeader('Origin')
            && $this->getTokenRequestOrigin($request) !== $this->getTokenRequestSchemeAndHttpHost($request);
    }

    private function getTokenRequestOrigin(ServerRequestInterface $request): string
    {
        $value = $request->getHeader('Origin');

        return reset($value);
    }

    private function getTokenRequestSchemeAndHttpHost(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $result = $uri->getScheme() . '://' . $uri->getHost();
        $port = $uri->getPort();
        if (null !== $port) {
            $result .= ':' . (string)$port;
        }

        return $result;
    }

    private function isAllowedOrigin(string $origin): bool
    {
        /** @var string[] $allowedOrigins */
        $allowedOrigins = $this->getParameter('oro_oauth2_server.cors.allow_origins');

        foreach ($allowedOrigins as $allowedOrigin) {
            if ('*' === $allowedOrigin || $origin === $allowedOrigin) {
                return true;
            }
        }

        return false;
    }

    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->get(AuthorizationServer::class);
        } catch (\LogicException $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            throw CryptKeyNotFoundException::create($e);
        }
    }

    private function getLogger(): LoggerInterface
    {
        return $this->get(LoggerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                SuccessHandler::class,
                ExceptionHandler::class,
                AuthorizationServer::class,
                LoggerInterface::class,
            ]
        );
    }
}
