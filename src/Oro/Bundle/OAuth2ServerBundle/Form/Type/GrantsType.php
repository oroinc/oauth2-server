<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Form\Transformer\GrantTypeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form type for grants field.
 */
class GrantsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['multiple']) {
            $builder->addModelTransformer(new GrantTypeTransformer());
        }
    }

    #[\Override]
    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
