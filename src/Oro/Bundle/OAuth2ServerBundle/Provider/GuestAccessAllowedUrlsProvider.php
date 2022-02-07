<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\FrontendBundle\GuestAccess\Provider\GuestAccessAllowedUrlsProviderInterface;

/**
 * Provides a list of patterns for URLs for which an access is granted for non-authenticated visitors.
 */
class GuestAccessAllowedUrlsProvider implements GuestAccessAllowedUrlsProviderInterface
{
    /** @var string[] */
    private $allowedUrls = [];

    /**
     * Adds a pattern to the list of allowed URL patterns.
     */
    public function addAllowedUrlPattern(string $pattern): void
    {
        $this->allowedUrls[] = $pattern;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllowedUrlsPatterns(): array
    {
        return $this->allowedUrls;
    }
}
