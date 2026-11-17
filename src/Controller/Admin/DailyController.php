<?php

namespace App\Controller\Admin;

use App\Repository\GameRepository;
use App\Shared\Shop\DailyProgram;
use InvalidArgumentException;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/engage/daily', name: 'daily_')]
class DailyController extends AbstractAdminController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    #[Csrf('admin-daily')]
    public function index(Request $request, DailyProgram $program, GameRepository $games): Response
    {
        $this->allow('engage.manage');
        $config = $program->editable();

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();

            try {
                $config = $program->normalize($data);
                $program->schedule($config);
                $this->audit('engage.daily', count($config['cycle']) . ' / ' . count($config['challenges']));

                return $this->done('admin_daily_index', [], 'admin.daily.saved');
            } catch (InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('@admin/pages/engage/daily.html.twig', ['config' => $config, 'submitted' => $data, 'products' => $program->candidates(), 'games' => $games->findAll(['name' => 'ASC'])], 422);
            }
        }

        return $this->render('@admin/pages/engage/daily.html.twig', ['config' => $config, 'submitted' => null, 'products' => $program->candidates(), 'games' => $games->findAll(['name' => 'ASC'])]);
    }
}