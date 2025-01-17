<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Form\Transformer\GrantTypeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The hidden form type for grants field.
 */
class HiddenGrantsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new GrantTypeTransformer());
    }

    #[\Override]
    public function getParent(): ?string
    {
        return HiddenType::class;
    }
}
