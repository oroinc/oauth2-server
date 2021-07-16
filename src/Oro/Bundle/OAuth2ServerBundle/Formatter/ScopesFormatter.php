<?php

namespace Oro\Bundle\OAuth2ServerBundle\Formatter;

use Oro\Bundle\UIBundle\Formatter\FormatterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The formatter for OAuth 2.0 scopes.
 */
class ScopesFormatter implements FormatterInterface
{
    /** @var TranslatorInterface */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function format($value, array $formatterArguments = [])
    {
        if (!$value) {
            return $this->getDefaultValue();
        }

        return implode(', ', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        return $this->translator->trans('oro.oauth2server.scopes.all');
    }
}
