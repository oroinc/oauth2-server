<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form for OAuth 2.0 client without owner field.
 */
class ClientType extends AbstractClientType
{
    /**
     * @param ClientOwnerOrganizationsProvider $organizationsProvider
     */
    public function __construct(ClientOwnerOrganizationsProvider $organizationsProvider)
    {
        parent::__construct($organizationsProvider);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_oauth2_client';
    }
}
