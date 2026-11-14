<?php

namespace App\Controller\Admin;

use App\Admin\Studio\DesignManager;
use App\Entity\Game;
use App\Repository\GameMemberRepository;
use App\Repository\GameRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Exception\AccessDeniedException;

#[Route('/studio', name: 'studio_design_')]
class StudioDesignController extends AbstractController
{
    public function __construct(
        protected DesignManager $designs,
        protected GameRepository $games,
        protected GameMemberRepository $members,
    ) {
    }

    #[Route('/{slug}/design', name: 'index', methods: ['GET'])]
    public function index(string $slug): Response
    {
        $game = $this->find($slug);

        return $this->render('@admin/pages/studio/design.html.twig', [
            'game' => $game,
            'rules' => $this->designs->load($game),
            'fonts' => DesignManager::FONTS,
            'shadows' => DesignManager::SHADOWS,
            'lead' => $this->members->findOneBy(['game' => $game, 'user' => $this->getUser()])?->isLead() ?? false,
        ]);
    }

    #[Route('/{slug}/design', name: 'save', methods: ['POST'])]
    #[Csrf('studio-design-{slug}')]
    public function save(string $slug, Request $request): Response
    {
        $rules = $request->toArray()['rules'] ?? [];
        $saved = $this->designs->save($this->find($slug), is_array($rules) ? $rules : []);

        return $this->json(['rules' => (object) $saved, 'css' => $this->designs->css($saved)]);
    }

    protected function find(string $slug): Game
    {
        $game = $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $member = $this->members->findOneBy(['game' => $game, 'user' => $this->getUser()]);

        if ($member === null || $member->getRole() === GameMemberRoleEnum::TESTER) {
            throw new AccessDeniedException();
        }

        return $game;
    }
}
