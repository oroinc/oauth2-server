<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * The utility class that can be used to check whether back-office or storefront API features are enabled or not.
 */
class ApiFeatureChecker
{
    private const API_FEATURE          = 'web_api';
    private const FRONTEND_API_FEATURE = 'frontend_web_api';

    /** @var FeatureChecker */
    private $featureChecker;

    /**
     * @param FeatureChecker $featureChecker
     */
    public function __construct(FeatureChecker $featureChecker)
    {
        $this->featureChecker = $featureChecker;
    }

    /**
     * @param string $ownerClass
     *
     * @return bool
     */
    public function isEnabledByClientOwnerClass(string $ownerClass): bool
    {
        if (is_a($ownerClass, User::class, true)) {
            return $this->isBackendApiEnabled();
        }

        return $this->isFrontendApiEnabled();
    }

    /**
     * @param Client $client
     *
     * @return bool
     */
    public function isEnabledByClient(Client $client): bool
    {
        if ($client->isFrontend()) {
            return $this->isFrontendApiEnabled();
        }

        return $this->isBackendApiEnabled();
    }

    /**
     * @return bool
     */
    public function isBackendApiEnabled(): bool
    {
        return $this->featureChecker->isFeatureEnabled(self::API_FEATURE);
    }

    /**
     * @return bool
     */
    public function isFrontendApiEnabled(): bool
    {
        return $this->featureChecker->isFeatureEnabled(self::FRONTEND_API_FEATURE);
    }
}
