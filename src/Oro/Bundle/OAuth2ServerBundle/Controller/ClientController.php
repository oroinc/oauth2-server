<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\ClientType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller covers widget-related functionality for OAuth 2.0 Client entity.
 */
class ClientController extends Controller
{
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
        $entityRoutingHelper = $this->get('oro_entity.routing_helper');
        $ownerEntityClass = $entityRoutingHelper->getEntityClassName($request);
        $ownerEntityId = (int)$entityRoutingHelper->getEntityId($request);

        if (!$this->getClientManager()->isCreationGranted($ownerEntityClass, $ownerEntityId)) {
            throw $this->createAccessDeniedException();
        }

        $entity = new Client();
        $entity->setOwnerEntity($ownerEntityClass, $ownerEntityId);

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
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function deleteAction(Client $entity): Response
    {
        $this->checkModificationAccess($entity);

        $this->getClientManager()->deleteClient($entity);

        return $this->noContent();
    }

    /**
     * @Route(
     *     "/activate/{id}",
     *     name="oro_oauth2_server_client_activate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function activateAction(Client $entity): Response
    {
        $this->checkModificationAccess($entity);

        $this->getClientManager()->activateClient($entity);

        return $this->noContent();
    }

    /**
     * @Route(
     *     "/deactivate/{id}",
     *     name="oro_oauth2_server_client_deactivate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     *
     * @param Client $entity
     *
     * @return Response
     */
    public function deactivateAction(Client $entity): Response
    {
        $this->checkModificationAccess($entity);

        $this->getClientManager()->deactivateClient($entity);

        return $this->noContent();
    }

    /**
     * @param Request $request
     * @param Client  $entity
     *
     * @return array
     */
    private function update(Request $request, Client $entity): array
    {
        return $this->get('oro_form.update_handler')->update(
            $entity,
            $this->getForm($entity),
            null,
            $request,
            null,
            $this->getFormTemplateDataProvider()
        );
    }

    /**
     * @return Response
     */
    private function noContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
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
        return $this->get('oro_oauth2_server.client_manager');
    }

    /**
     * @param Client $entity
     *
     * @return FormInterface
     */
    private function getForm(Client $entity): FormInterface
    {
        return $this->container->get('form.factory')
            ->createNamed('oro_oauth2_client', ClientType::class, $entity);
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
