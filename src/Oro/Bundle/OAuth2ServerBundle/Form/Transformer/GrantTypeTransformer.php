<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms grant type between array and string representation.
 */
class GrantTypeTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (null === $value || [] === $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            throw new TransformationFailedException(sprintf(
                'Expected argument of type "array" or "string", "%s" given.',
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }

        if (count($value) > 1) {
            throw new TransformationFailedException('The value must contain not more than 1 element.');
        }

        return reset($value);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new TransformationFailedException(sprintf(
                'Expected argument of type "string" or "array", "%s" given.',
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }

        return [$value];
    }
}
