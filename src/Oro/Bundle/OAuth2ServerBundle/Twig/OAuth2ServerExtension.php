<?php

namespace Oro\Bundle\OAuth2ServerBundle\Twig;

use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides the following Twig functions:
 *   - oro_oauth2_api_view_label - to get the label of a specific API view
 */
class OAuth2ServerExtension extends AbstractExtension
{
    public function __construct(
        private readonly ApiDocViewProvider $apiDocViewProvider
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('oro_oauth2_api_view_label', [$this, 'getApiViewLabel'])
        ];
    }

    public function getApiViewLabel(bool $isFrontend, string $viewName): string
    {
        $views = $this->apiDocViewProvider->getViews($isFrontend);

        return $views[$viewName] ?? '';
    }
}
