<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\ApiBundle\Request\RequestType;

/**
 * Provides configuration of API views for a specific request type.
 */
class ApiDocViewProvider
{
    private ?array $viewRequestTypes = null;

    public function __construct(
        private readonly array $views
    ) {
    }

    /**
     * @return array [view name => view label, ...]
     */
    public function getViews(bool $isFrontend): array
    {
        $result = [];
        foreach ($this->views as $name => [$label, $requestTypes]) {
            if (!($isFrontend xor \in_array('frontend', $requestTypes ?? [], true))) {
                $result[$name] = $label;
            }
        }

        return $result;
    }

    /**
     * @return array [view name => view label, ...]
     */
    public function getViewsByRequestType(RequestType $requestType): array
    {
        $result = [];
        $requestTypeAspects = $requestType->toArray();
        $filteredRequestTypeAspects = $this->filterRequestType($requestTypeAspects);
        foreach ($this->views as $name => [$label, $requestTypes]) {
            if (!$requestTypes && !$requestTypeAspects) {
                // old backoffice API
                $result[$name] = $label;
            } elseif ($requestTypes && $this->matchRequestType($filteredRequestTypeAspects, $requestTypes)) {
                $result[$name] = $label;
            }
        }

        return $result;
    }

    private function matchRequestType(array $requestTypes, array $viewRequestTypes): bool
    {
        if (\count($requestTypes) !== \count($viewRequestTypes)) {
            return false;
        }

        foreach ($requestTypes as $requestTypeAspect) {
            if (!\in_array($requestTypeAspect, $viewRequestTypes, true)) {
                return false;
            }
        }

        return true;
    }

    private function filterRequestType(array $requestTypes): array
    {
        if ($requestTypes) {
            $this->ensureViewRequestTypesInitialized();

            $toRemoveKeys = [];
            foreach ($requestTypes as $key => $requestTypeAspect) {
                if (!\in_array($requestTypeAspect, $this->viewRequestTypes, true)) {
                    $toRemoveKeys[] = $key;
                }
            }
            if ($toRemoveKeys) {
                foreach ($toRemoveKeys as $key) {
                    unset($requestTypes[$key]);
                }
                $requestTypes = array_values($requestTypes);
            }
        }

        return $requestTypes;
    }

    private function ensureViewRequestTypesInitialized(): void
    {
        if (null === $this->viewRequestTypes) {
            $this->viewRequestTypes = [];
            foreach ($this->views as [, $requestTypes]) {
                if ($requestTypes) {
                    foreach ($requestTypes as $requestTypeAspect) {
                        $this->viewRequestTypes[] = $requestTypeAspect;
                    }
                }
            }
            $this->viewRequestTypes = array_values(array_unique($this->viewRequestTypes));
        }
    }
}
