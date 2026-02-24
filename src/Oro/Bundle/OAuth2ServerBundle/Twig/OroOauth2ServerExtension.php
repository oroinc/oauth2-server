<?php

namespace Oro\Bundle\OAuth2ServerBundle\Twig;

use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides the following Twig functions:
 *   - oro_oauth2_api_view_label - to get the label of a specific API view
 *   - oro_oauth2_api_view_labels - to get labels of specific API views
 *   - oro_oauth2_api_view_description - to get the description of a specific API view
 */
class OroOauth2ServerExtension extends AbstractExtension
{
    public function __construct(
        private readonly ApiDocViewProvider $apiDocViewProvider
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('oro_oauth2_api_view_label', [$this, 'getApiViewLabel']),
            new TwigFunction('oro_oauth2_api_view_labels', [$this, 'getApiViewLabels']),
            new TwigFunction('oro_oauth2_api_view_description', [$this, 'getApiViewDescription']),
        ];
    }

    public function getApiViewLabel(bool $isFrontend, string $viewName): string
    {
        $views = $this->apiDocViewProvider->getViews($isFrontend);

        return $views[$viewName] ?? '';
    }

    public function getApiViewLabels(bool $isFrontend, array $viewNames): array
    {
        $result = [];
        $viewLabels = $this->apiDocViewProvider->getViewLabels($isFrontend, $viewNames);
        foreach ($viewLabels as $viewLabel) {
            $result[] = $viewLabel ?? '';
        }

        return $result;
    }

    public function getApiViewDescription(string $viewName): string
    {
        return $this->apiDocViewProvider->getViewDescription($viewName) ?? '';
    }
}
