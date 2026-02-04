<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\OAuth2ServerBundle\Generator\OAuth2CodeGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Returns OAuth server metadata.
 */
class MetadataController
{
    public function __construct(
        private readonly array $routeNames,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function metadataAction(bool $allowAccess = false): Response
    {
        if (!$allowAccess) {
            /**
             * direct access to this endpoint is not allowed, the well-known endpoint should be used instead
             * @see WellKnownMetadataController::metadataAction
             */
            throw new NotFoundHttpException();
        }

        return new JsonResponse([
            'issuer' => $this->generateAbsoluteUrl($this->routeNames['issuer']),
            'authorization_endpoint' => $this->generateAbsoluteUrl($this->routeNames['authorization_endpoint']),
            'token_endpoint' => $this->generateAbsoluteUrl($this->routeNames['token_endpoint']),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => [OAuth2CodeGenerator::CODE_CHALLENGE_METHOD],
            'client_id_metadata_document_supported' => true
        ]);
    }

    private function generateAbsoluteUrl(string $routeName): string
    {
        return $this->urlGenerator->generate($routeName, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
