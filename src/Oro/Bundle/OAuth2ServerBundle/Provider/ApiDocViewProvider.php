<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides configuration of API views for a specific request type.
 */
class ApiDocViewProvider
{
    private ?array $viewRequestTypes = null;

    public function __construct(
        private readonly array $views,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @return array<string, string|null> [view name => view label, ...]
     */
    public function getViews(bool $isFrontend): array
    {
        $result = [];
        foreach ($this->views as $name => [$label, $requestTypes]) {
            if (!($isFrontend xor \in_array('frontend', $requestTypes ?? [], true))) {
                $result[$name] = $this->getViewLabel($name, $label);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string|null> [view name => view label, ...]
     */
    public function getViewsByRequestType(RequestType $requestType): array
    {
        $result = [];
        $requestTypeAspects = $requestType->toArray();
        $filteredRequestTypeAspects = $this->filterRequestType($requestTypeAspects);
        foreach ($this->views as $name => [$label, $requestTypes]) {
            if (!$requestTypes && !$requestTypeAspects) {
                // old backoffice API
                $result[$name] = $this->getViewLabel($name, $label);
            } elseif ($requestTypes && $this->matchRequestType($filteredRequestTypeAspects, $requestTypes)) {
                $result[$name] = $this->getViewLabel($name, $label);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string|null> [view name => view label, ...]
     */
    public function getViewLabels(bool $isFrontend, array $viewNames): array
    {
        $result = [];
        $views = $this->getViews($isFrontend);
        foreach ($views as $name => $label) {
            if (\in_array($name, $viewNames, true)) {
                $result[$name] = $label;
            }
        }

        return $result;
    }

    public function getViewDescription(string $name): ?string
    {
        $translationId = \sprintf('oro.api.open_api.views.%s.description', $name);
        $description = $this->translator->trans($translationId);
        if ($description === $translationId) {
            return null;
        }

        return $description;
    }

    private function getViewLabel(string $name, ?string $label): ?string
    {
        if (!$label) {
            return $label;
        }

        $translationId = \sprintf('oro.api.open_api.views.%s.label', $name);
        $translatedLabel = $this->translator->trans($translationId);
        if ($translatedLabel === $translationId) {
            $translatedLabel = $label;
        }

        return $translatedLabel;
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
