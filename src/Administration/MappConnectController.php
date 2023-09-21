<?php declare(strict_types=1);

namespace Mapp\Connect\Shopware\Administration;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Mapp\Connect\Shopware\Service\MappConnectService;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class MappConnectController extends AbstractController
{

    private $mappConnectService;

    public function __construct(MappConnectService $mappConnectService)
    {
        $this->mappConnectService = $mappConnectService;
    }

    /**
     * @Route("/api/mappconnect/connection-status", name="api.action.mappconnect.connection-status", methods={"GET"})
     * @Route("/api/v{version}/mappconnect/connection-status", name="api.action.mappconnect.connection-status.failback", methods={"GET"})
     * @Route(defaults={"_acl"={}})
     */
    public function getConnectionStatus(Request $request, Context $context): Response
    {
        $status = $this->mappConnectService->getConnectionStatus();
        return new JsonResponse( $status );
    }

    /**
     * @Route("/api/mappconnect/groups", name="api.action.mappconnect.groups", methods={"GET"})
     * @Route("/api/v{version}/mappconnect/groups", name="api.action.mappconnect.groups.failback", methods={"GET"})
     * @Route(defaults={"_acl"={}})
     */
    public function getGroups(Request $request, Context $context): Response
    {
        $groups = $this->mappConnectService->getGroups();
        return new JsonResponse( $groups );
    }

    /**
     * @Route("/api/mappconnect/messages", name="api.action.mappconnect.messages", methods={"GET"})
     * @Route("/api/v{version}/mappconnect/messages", name="api.action.mappconnect.messages.failback", methods={"GET"})
     * @Route(defaults={"_acl"={}})
     */
    public function getMessages(Request $request, Context $context): Response
    {
        $messages = $this->mappConnectService->getMessages();
        return new JsonResponse( $messages );
    }
}
