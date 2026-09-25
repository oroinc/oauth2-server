<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

/**
 * Provides the URL of the application that started a Session Transfer.
 * Storefront pages rendered in Session Transfer mode link back to this URL.
 */
class SessionTransferReturnUrlProvider
{
    public function __construct(private readonly string $returnUrl)
    {
    }

    /**
     * Gets the configured return URL without a trailing slash, so callers can append paths to it.
     */
    public function getReturnUrl(): string
    {
        return rtrim($this->returnUrl, '/');
    }
}
