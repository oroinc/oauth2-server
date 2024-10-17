<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Client;

use Oro\Bundle\OAuth2ServerBundle\Controller\AuthorizeClientController;
use Oro\Bundle\OAuth2ServerBundle\Generator\OAuth2CodeGenerator;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ClientRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Makes an internal request to provide an authorization code.
 */
class AuthorizationCodeClient
{
    public function __construct(
        private HttpKernelInterface $httpKernel,
        private ClientRepository $clientRepository,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @param string $clientIdentifier OAuth2 client identifier.
     *
     * @return array{code: string, code_verifier: string}|null
     */
    public function getAuthCode(string $clientIdentifier): ?array
    {
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        if (!$clientEntity) {
            return null;
        }

        $codeVerifier = OAuth2CodeGenerator::generateCodeVerifier();

        $response = $this->sendRequest([
            'response_type' => 'code',
            'client_id' => $clientIdentifier,
            'code_challenge' => OAuth2CodeGenerator::generateCodeChallenge($codeVerifier),
            'code_challenge_method' => OAuth2CodeGenerator::CODE_CHALLENGE_METHOD,
        ], $clientEntity->isFrontend());

        $location = $response->headers->get('Location');
        if (!$location) {
            return null;
        }

        $locationQuery = (string)parse_url($location, PHP_URL_QUERY);
        parse_str($locationQuery, $queryParameters);

        if (empty($queryParameters['code'])) {
            return null;
        }

        return ['code' => $queryParameters['code'], 'code_verifier' => $codeVerifier];
    }

    private function sendRequest(array $requestBody, bool $isFrontend): Response
    {
        $attributes = [
            '_controller' => AuthorizeClientController::class . '::authorizeAction',
            'type' => $isFrontend ? 'frontend' : 'backoffice',
        ];

        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest) {
            $subRequest = $currentRequest->duplicate($requestBody, [], $attributes);
            if ($subRequest->getMethod() === Request::METHOD_POST) {
                $subRequest->request->set('grantAccess', 'true');
            }
        } else {
            $subRequest = Request::create('/', Request::METHOD_GET, $requestBody);
            $subRequest->attributes->add($attributes);
        }

        return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
