<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\FormBundle\Model\UpdateHandlerFacade;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\ClientType;
use Oro\Bundle\SecurityBundle\Annotation\CsrfProtection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller covers widget-related functionality for OAuth 2.0 Client entity.
 */
class ClientController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [
            EntityRoutingHelper::class,
            UpdateHandlerFacade::class,
            ClientManager::class,
            FormFactoryInterface::class
        ]);
    }

    /**
     * @Route(
     *     "/create",
     *     name="oro_oauth2_server_client_create",
     *     methods={"GET", "POST"}
     * )
     * @Template("OroOAuth2ServerBundle:Client:create.html.twig")
     *
     * @param Request $request
     *
     * @return array
     */
    public function createAction(Request $request): array
    {
        if (!$this->getClientManager()->isCreationGranted()) {
            throw $this->createAccessDeniedException();
        }


        $entity = new Client();

        $entityRoutingHelper = $this->get(EntityRoutingHelper::class);
        $ownerEntityClass = $entityRoutingHelper->getEntityClassName($request);
        if ($ownerEntityClass) {
            $ownerEntityId = (int)$entityRoutingHelper->getEntityId($request);
            $entity->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        }

        return $this->update($request, $entity);
    }

    /**
     * @Route(
     *     "/update/{id}",
     *     name="oro_oauth2_server_client_update",
     *     methods={"GET", "POST"},
     *     requirements={"id"="\d+"}
     * )
     * @Template("OroOAuth2ServerBundle:Client:update.html.twig")
     *
     * @param Request $request
     * @param Client  $entity
     *
     * @return array
     */
    public function updateAction(Request $request, Client $entity): array
    {
        $this->checkModificationAccess($entity);

        return $this->update($request, $entity);
    }

    /**
     * @Route(
     *     "/delete/{id}",
     *     name="oro_oauth2_server_client_delete",
     *     methods={"DELETE"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function deleteAction(Client $entity): Response
    {
        if (!$this->getClientManager()->isDeletionGranted($entity)) {
            throw $this->createAccessDeniedException();
        }

        $this->getClientManager()->deleteClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @Route(
     *     "/activate/{id}",
     *     name="oro_oauth2_server_client_activate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function activateAction(Client $entity): Response
    {
        $this->checkModificationAccess($entity);

        $this->getClientManager()->activateClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @Route(
     *     "/deactivate/{id}",
     *     name="oro_oauth2_server_client_deactivate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function deactivateAction(Client $entity): Response
    {
        $this->checkModificationAccess($entity);

        $this->getClientManager()->deactivateClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @param Request $request
     * @param Client  $entity
     *
     * @return array
     */
    private function update(Request $request, Client $entity): array
    {
        return $this->get(UpdateHandlerFacade::class)->update(
            $entity,
            $this->getForm($entity),
            null,
            $request,
            null,
            $this->getFormTemplateDataProvider()
        );
    }

    /**
     * @param Client $entity
     */
    private function checkModificationAccess(Client $entity): void
    {
        if (!$this->getClientManager()->isModificationGranted($entity)) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @return ClientManager
     */
    private function getClientManager(): ClientManager
    {
        return $this->get(ClientManager::class);
    }

    /**
     * @param Client $entity
     *
     * @return FormInterface
     */
    private function getForm(Client $entity): FormInterface
    {
        return $this->get(FormFactoryInterface::class)
            ->createNamed(
                'oro_oauth2_client',
                ClientType::class,
                $entity,
                ['grant_types' => ['client_credentials']]
            );
    }

    /**
     * @return callable
     */
    private function getFormTemplateDataProvider(): callable
    {
        return function (Client $entity, FormInterface $form) {
            $formAction = null === $entity->getId()
                ? $this->generateUrl('oro_oauth2_server_client_create')
                : $this->generateUrl('oro_oauth2_server_client_update', ['id' => $entity->getId()]);

            return [
                'entity'     => $entity,
                'form'       => $form->createView(),
                'formAction' => $formAction
            ];
        };
    }
}
