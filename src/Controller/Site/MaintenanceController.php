<?php

namespace App\Controller\Site;

use App\Shared\Maintenance\Enum\MaintenancePageEnum;
use App\Shared\Maintenance\MaintenanceManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/maintenance', name: 'maintenance_')]
class MaintenanceController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(MaintenanceManager $maintenance): Response
    {
        $current = $maintenance->site() ?? $maintenance->nextSite();

        if ($current === null) {
            return $this->redirectToRoute('site_home_index');
        }

        return $this->render('@site/pages/maintenance/show.html.twig', [
            'maintenance' => $current,
            'pages' => array_map(fn (string $page): MaintenancePageEnum => MaintenancePageEnum::from($page), $current->getPages()),
        ], $current->isRunning() ? 503 : 200);
    }
}
