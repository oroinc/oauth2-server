<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles well-known URLs for OAuth server metadata.
 */
class WellKnownMetadataController
{
    public function __construct(
        private readonly array $metadataControllers,
        private readonly ContainerInterface $container
    ) {
    }

    public function metadataAction(string $metadataPath): Response
    {
        $metadataController = $this->getMetadataController($metadataPath);
        if (null === $metadataController) {
            return new Response(
                'The OAuth server not found.',
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'text/plain']
            );
        }

        return $metadataController->metadataAction(true);
    }

    private function getMetadataController(string $metadataPath): ?MetadataController
    {
        $metadataControllerId = $this->metadataControllers['/' . $metadataPath] ?? null;

        return null !== $metadataControllerId
            ? $this->container->get($metadataControllerId)
            : null;
    }
}
