<?php

namespace App\Controller\Admin;

use App\Admin\Audit\AuditLogger;
use App\Admin\Dashboard\DashboardManager;
use App\Admin\Security\AdminAccess;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

class DashboardController extends AbstractAdminController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function home(AdminAccess $access): Response
    {
        $route = $access->home($this->getUser()) ?? throw $this->createAccessDeniedException();

        return $this->redirectToRoute($route);
    }

    #[Route('/dashboard', name: 'dashboard_index', methods: ['GET'])]
    public function index(Request $request, DashboardManager $dashboard, AuditLogger $logs): Response
    {
        $period = array_key_exists((string) $request->query->get('period'), DashboardManager::PERIODS) ? (string) $request->query->get('period') : 'day';

        return $this->render('@admin/pages/dashboard/index.html.twig', [
            'period' => $period,
            'periods' => array_keys(DashboardManager::PERIODS),
            'kpis' => $dashboard->kpis($period),
            'activity' => $dashboard->activity(),
            'services' => $dashboard->services(),
            'top' => $dashboard->topGames($period),
            'logs' => $logs->latest(8),
        ]);
    }
}
