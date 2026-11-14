<?php

namespace App\Controller\Admin;

use App\Admin\Studio\EditorManager;
use App\Admin\Studio\ReviewManager;
use App\Admin\Studio\StudioManager;
use App\Entity\Game;
use App\Repository\GameMemberRepository;
use App\Repository\GameRepository;
use App\Repository\GameReviewRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Exception\AccessDeniedException;

#[Route('/studio', name: 'studio_editor_')]
class StudioEditorController extends AbstractController
{
    public function __construct(
        protected EditorManager $editor,
        protected GameRepository $games,
        protected GameMemberRepository $members,
    ) {
    }

    #[Route('/{slug}/editor', name: 'index', methods: ['GET'])]
    public function index(string $slug, GameReviewRepository $reviews, StudioManager $studio): Response
    {
        $game = $this->find($slug);

        return $this->render('@admin/pages/studio/editor.html.twig', [
            'game' => $game,
            'tree' => $this->editor->tree($game),
            'lead' => $this->isLead($game),
            'review' => $reviews->findPending($game),
            'nocode' => $studio->origin($game) !== 'coded',
        ]);
    }

    #[Route('/{slug}/editor/file', name: 'read', methods: ['GET'])]
    public function read(string $slug, Request $request): Response
    {
        return $this->json(['content' => $this->editor->read($this->find($slug), (string) $request->query->get('path'))]);
    }

    #[Route('/{slug}/editor/file', name: 'save', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function save(string $slug, Request $request): Response
    {
        $data = $request->toArray();
        $this->editor->write($this->find($slug), (string) ($data['path'] ?? ''), (string) ($data['content'] ?? ''));

        return $this->json(['ok' => true]);
    }

    #[Route('/{slug}/editor/new', name: 'create', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function create(string $slug, Request $request): Response
    {
        $game = $this->find($slug);
        $this->editor->create($game, (string) ($request->toArray()['path'] ?? ''));

        return $this->json(['tree' => $this->editor->tree($game)]);
    }

    #[Route('/{slug}/editor/folder', name: 'folder', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function folder(string $slug, Request $request): Response
    {
        $game = $this->find($slug);
        $this->editor->createFolder($game, (string) ($request->toArray()['path'] ?? ''));

        return $this->json(['tree' => $this->editor->tree($game)]);
    }

    #[Route('/{slug}/editor/delete', name: 'delete', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function delete(string $slug, Request $request): Response
    {
        $game = $this->find($slug);
        $this->editor->delete($game, (string) ($request->toArray()['path'] ?? ''));

        return $this->json(['tree' => $this->editor->tree($game)]);
    }

    #[Route('/{slug}/editor/rename', name: 'rename', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function rename(string $slug, Request $request): Response
    {
        $game = $this->find($slug);
        $data = $request->toArray();
        $this->editor->rename($game, (string) ($data['from'] ?? ''), (string) ($data['to'] ?? ''));

        return $this->json(['tree' => $this->editor->tree($game)]);
    }

    #[Route('/{slug}/editor/validate', name: 'validate', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function check(string $slug): Response
    {
        return $this->json(['error' => $this->editor->validate($this->find($slug))]);
    }

    #[Route('/{slug}/editor/publish', name: 'publish', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function publish(string $slug, ReviewManager $reviews): Response
    {
        $game = $this->find($slug);

        if (!$this->isLead($game)) {
            throw new AccessDeniedException();
        }

        $error = $this->editor->validate($game);
        $version = $error === null ? $this->editor->publish($game)->getVersion() : null;

        if ($version !== null) {
            $reviews->published($game, $this->getUser(), $version);
        }

        return $this->json(['error' => $error, 'version' => $version]);
    }

    #[Route('/{slug}/editor/review', name: 'review', methods: ['POST'])]
    #[Csrf('studio-editor-{slug}')]
    public function review(string $slug, Request $request, ReviewManager $reviews, GameReviewRepository $repository): Response
    {
        $game = $this->find($slug);
        $already = $repository->findPending($game) !== null;
        $reviews->request($game, $this->getUser(), (string) ($request->toArray()['message'] ?? ''));

        return $this->json(['ok' => true, 'already' => $already]);
    }

    protected function isLead(Game $game): bool
    {
        return $this->members->findOneBy(['game' => $game, 'user' => $this->getUser()])?->isLead() ?? false;
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