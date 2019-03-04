<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form for OAuth 2.0 client.
 */
class ClientType extends AbstractType
{
    /** @var string[] */
    private $grantTypes;

    /**
     * @param string[] $grantTypes
     */
    public function __construct(array $grantTypes)
    {
        $this->grantTypes = $grantTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label'    => 'oro.oauth2server.client.name.label',
                'tooltip'  => 'oro.oauth2server.client.name.description',
                'required' => true
            ])
            ->add('active', CheckboxType::class, [
                'label'   => 'oro.oauth2server.client.active.label',
                'tooltip' => 'oro.oauth2server.client.active.description'
            ])
            ->add('grants', ChoiceType::class, [
                'label'    => 'oro.oauth2server.client.grants.label',
                'tooltip'  => 'oro.oauth2server.client.grants.description',
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'choices'  => $this->getGrantTypes()
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => Client::class,
            'csrf_token_id' => 'oro_oauth2_client'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_oauth2_client';
    }

    /**
     * @return array
     */
    private function getGrantTypes(): array
    {
        return array_combine(
            array_map(
                function ($v) {
                    return 'oro.oauth2server.grant_types.' . $v;
                },
                $this->grantTypes
            ),
            $this->grantTypes
        );
    }
}
