<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form for OAuth 2.0 client.
 */
class ClientType extends AbstractType
{
    /** @var string[] */
    private $grantTypes;

    /** @var ClientOwnerOrganizationsProvider */
    private $organizationsProvider;

    /**
     * @param string[]                         $grantTypes
     * @param ClientOwnerOrganizationsProvider $organizationsProvider
     */
    public function __construct(
        array $grantTypes,
        ClientOwnerOrganizationsProvider $organizationsProvider
    ) {
        $this->grantTypes = $grantTypes;
        $this->organizationsProvider = $organizationsProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                $this->addOrganizationField($event);
                $this->addIdentifierField($event);
            })
            ->add('name', TextType::class, [
                'label'    => 'oro.oauth2server.client.name.label',
                'tooltip'  => 'oro.oauth2server.client.name.description',
                'required' => true
            ])
            ->add('active', CheckboxType::class, [
                'label'   => 'oro.oauth2server.client.active.label',
                'tooltip' => 'oro.oauth2server.client.active.description'
            ]);
        /** BAP-18427: uncomment this block when other grant types is implemented
            ->add('grants', ChoiceType::class, [
                'label'    => 'oro.oauth2server.client.grants.label',
                'tooltip'  => 'oro.oauth2server.client.grants.description',
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'choices'  => $this->getGrantTypes()
            ]);
         */
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

    /**
     * Adds organization field to a new client creation form if multi organization is supported.
     *
     * @param FormEvent $event
     */
    private function addOrganizationField(FormEvent $event): void
    {
        /** @var Client $client */
        $client = $event->getData();
        if (null !== $client->getId()) {
            return;
        }

        if (!$this->organizationsProvider->isMultiOrganizationSupported()) {
            return;
        }

        $organizations = $this->organizationsProvider->getClientOwnerOrganizations(
            $client->getOwnerEntityClass(),
            $client->getOwnerEntityId()
        );
        if ($this->organizationsProvider->isOrganizationSelectorRequired($organizations)) {
            $event->getForm()->add('organization', EntityType::class, [
                'label'                => 'oro.organization.entity_label',
                'class'                => Organization::class,
                'choices'              => $this->organizationsProvider->sortOrganizations($organizations),
                'translatable_options' => false
            ]);
        }
    }

    /**
     * Adds read-only identifier field to an existing client edit form.
     *
     * @param FormEvent $event
     */
    private function addIdentifierField(FormEvent $event): void
    {
        /** @var Client $client */
        $client = $event->getData();
        if (null === $client->getId()) {
            return;
        }

        $event->getForm()->add('identifier', TextType::class, [
            'label'    => 'oro.oauth2server.client.identifier.label',
            'tooltip'  => 'oro.oauth2server.client.identifier.description',
            'required' => false,
            'disabled' => true
        ]);
    }
}
