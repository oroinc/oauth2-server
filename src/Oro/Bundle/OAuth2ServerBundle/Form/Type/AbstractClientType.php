<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

/**
 * The base form for OAuth 2.0 client.
 */
abstract class AbstractClientType extends AbstractType
{
    /** @var ClientOwnerOrganizationsProvider */
    private $organizationsProvider;

    public function __construct(ClientOwnerOrganizationsProvider $organizationsProvider)
    {
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
                $this->modifyGrantField($event);
                $this->addRedirectUrisField($event);
            })
            ->add('name', TextType::class, [
                'label'   => 'oro.oauth2server.client.name.label',
                'tooltip' => 'oro.oauth2server.client.name.description'
            ])
            ->add('active', CheckboxType::class, [
                'label'   => 'oro.oauth2server.client.active.label',
                'tooltip' => 'oro.oauth2server.client.active.description'
            ]);

        $this->addGrantField(
            $builder,
            $options['show_grants'],
            $options['grant_types'],
            $options['multiple_grants']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('data_class', Client::class)
            ->setDefault('csrf_token_id', 'oro_oauth2_client')
            ->setRequired('grant_types')
            ->setAllowedTypes('grant_types', 'array')
            ->setNormalizer('grant_types', function (Options $options, $value) {
                if (!$value) {
                    throw new InvalidOptionsException('The option "grant_types" must not be empty.');
                }

                return $value;
            })
            ->setDefault('show_grants', function (Options $options, $value) {
                if (null !== $value) {
                    return $value;
                }

                return count($options['grant_types']) > 1;
            })
            ->setAllowedTypes('show_grants', 'bool')
            ->setDefault('multiple_grants', false)
            ->setAllowedTypes('multiple_grants', 'bool');
    }

    /**
     * @param string[] $grantTypes
     *
     * @return array
     */
    private function getGrantTypes(array $grantTypes): array
    {
        sort($grantTypes);

        return array_combine(
            array_map(
                function ($v) {
                    return 'oro.oauth2server.grant_types.' . $v;
                },
                $grantTypes
            ),
            $grantTypes
        );
    }

    /**
     * Adds organization field to a new client creation form if multi organization is supported.
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

        if ($client->getOwnerEntityId()) {
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
    }

    /**
     * Adds read-only identifier field to an existing client edit form.
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

    /**
     * Replaces grants field to read-only field for an existing client edit form.
     */
    private function modifyGrantField(FormEvent $event): void
    {
        /** @var Client $client */
        $client = $event->getData();
        if (null === $client->getId()) {
            return;
        }

        $form = $event->getForm();
        $formConfig = $form->getConfig();
        $showGrants = $formConfig->getOption('show_grants');
        if (!$showGrants) {
            return;
        }

        $form->remove('grants');
        $this->addGrantField(
            $form,
            $showGrants,
            $formConfig->getOption('grant_types'),
            $formConfig->getOption('multiple_grants'),
            true
        );
    }

    /**
     * Adds the grants field to form.
     *
     * @param FormInterface|FormBuilderInterface $form
     * @param bool                               $showGrants
     * @param array                              $grantTypes
     * @param bool                               $multipleGrantTypes
     * @param bool                               $disabled
     */
    private function addGrantField(
        $form,
        bool $showGrants,
        array $grantTypes,
        bool $multipleGrantTypes,
        bool $disabled = false
    ): void {
        if ($showGrants) {
            $defaultGrantType = $multipleGrantTypes ? [] : null;
            if (count($grantTypes) === 1) {
                $defaultGrantType = reset($grantTypes);
            }
            $form
                ->add('grants', GrantsType::class, [
                    'label'      => 'oro.oauth2server.client.grants.label',
                    'tooltip'    => 'oro.oauth2server.client.grants.description',
                    'required'   => true,
                    'expanded'   => true,
                    'multiple'   => $multipleGrantTypes,
                    'choices'    => $this->getGrantTypes($grantTypes),
                    'empty_data' => $defaultGrantType,
                    'disabled'   => $disabled

                ]);
        } elseif (\in_array('client_credentials', $grantTypes, true)) {
            $form->add('grants', HiddenGrantsType::class, [
                'data' => 'client_credentials'
            ]);
        }
    }

    /**
     * Adds the redirectUris field to form.
     */
    private function addRedirectUrisField(FormEvent $event): void
    {
        /** @var Client $client */
        $client = $event->getData();
        if ($client->getId() && !\in_array('authorization_code', $client->getGrants(), true)) {
            return;
        }

        $form = $event->getForm();
        if (!$form->getConfig()->getOption('show_grants')) {
            return;
        }

        $form->add(
            'redirectUris',
            CollectionType::class,
            [
                'label'          => 'oro.oauth2server.client.redirect_uris.label',
                'tooltip'        => 'oro.oauth2server.client.redirect_uris.description',
                'add_label'      => 'oro.oauth2server.client.redirect_uris.add',
                'entry_type'     => UrlType::class,
                'entry_options'  => [
                    'default_protocol' => null,
                    'constraints'      => [new Url(), new NotBlank()]
                ],
                'error_bubbling' => false,
                'allow_add'      => true,
                'allow_delete'   => true,
                'prototype'      => true
            ]
        );
    }
}
