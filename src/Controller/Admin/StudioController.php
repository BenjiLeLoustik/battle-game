<?php

namespace App\Controller\Admin;

use App\Admin\Security\AdminAccess;
use App\Admin\Studio\NewGameRequest;
use App\Admin\Studio\ReviewManager;
use App\Admin\Studio\Enum\GameTaskStatusEnum;
use App\Admin\Studio\StudioManager;
use App\Entity\Game;
use App\Entity\GameMember;
use App\Repository\CategoryRepository;
use App\Repository\GameMemberRepository;
use App\Repository\GameReviewRepository;
use App\Repository\GameRepository;
use App\Repository\GameTaskRepository;
use App\Repository\UserRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/studio', name: 'studio_')]
class StudioController extends AbstractController
{
    public function __construct(
        protected StudioManager $studio,
        protected GameRepository $games
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, GameMemberRepository $members, GameTaskRepository $tasks): Response
    {
        $mine = $request->query->get('scope') !== 'all';
        $status = GameStatusEnum::tryFrom((string) $request->query->get('status'));
        $search = trim((string) $request->query->get('q'));

        return $this->render('@admin/pages/studio/index.html.twig', [
            'games' => $this->studio->list($this->getUser(), $mine, $status, $search),
            'teams' => $members->teams(),
            'tasks' => $tasks->countOpen(),
            'mine' => $mine,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'mine' => count($this->studio->list($this->getUser(), true, null, '')),
                'all' => count($this->studio->list($this->getUser(), false, null, '')),
            ],
            'statuses' => GameStatusEnum::cases(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('studio-new')]
    public function new(Request $request, UserRepository $users, CategoryRepository $categories): Response
    {
        $data = NewGameRequest::fromArray($request->request->all());
        $violations = null;

        if ($request->getMethod() === 'POST') {
            $violations = $this->validate($data);

            if (count($violations) === 0) {
                return $this->redirectToRoute('admin_studio_show', ['slug' => $this->studio->create($data, $this->getUser())->getSlug()]);
            }
        }

        return $this->render('@admin/pages/studio/new.html.twig', [
            'data' => $data,
            'violations' => $violations,
            'categories' => $categories->findOrdered(true),
            'admins' => $users->findAdmins(),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, GameManager $manager, GameMemberRepository $members, GameTaskRepository $tasks, GameReviewRepository $reviews, UserRepository $users): Response
    {
        $game = $this->find($slug);

        try {
            $manager->manifest($game->getDirectory());
            $error = null;
        } catch (GameManifestException $exception) {
            $error = $exception->getContext()['reason'];
        }

        return $this->render('@admin/pages/studio/show.html.twig', [
            'game' => $game,
            'error' => $error,
            'members' => $members->findBy(['game' => $game], ['id' => 'ASC']),
            'candidates' => $this->studio->candidates($game),
            'statuses' => GameStatusEnum::cases(),
            'roles' => [GameMemberRoleEnum::DEVELOPER, GameMemberRoleEnum::TESTER],
            'lead' => $this->studio->isLead($game, $this->getUser()),
            'can_transfer' => $this->canTransfer($game),
            'nocode' => $this->studio->origin($game) !== 'coded',
            'admins' => $users->findAdmins(),
            'review' => $reviews->findPending($game),
            'reviews' => $reviews->findLatest($game),
            'tasks' => $tasks->findByGame($game),
            'task_statuses' => GameTaskStatusEnum::cases(),
        ]);
    }

    #[Route('/{slug}/status', name: 'status', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function status(string $slug, Request $request): Response
    {
        $status = GameStatusEnum::tryFrom((string) $request->request->get('status'));

        if ($status !== null) {
            $this->studio->changeStatus($this->find($slug), $status, $this->getUser());
        }

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/members', name: 'member_add', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function addMember(string $slug, Request $request, UserRepository $users): Response
    {
        $user = $users->find((int) $request->request->get('user'));
        $role = GameMemberRoleEnum::tryFrom((string) $request->request->get('role'));

        if ($user !== null && $role !== null && $role !== GameMemberRoleEnum::LEAD && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->studio->addMember($this->find($slug), $user, $role);
        }

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/members/{id}/remove', name: 'member_remove', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function removeMember(string $slug, int $id, GameMemberRepository $members): Response
    {
        $member = $members->find($id);

        if ($member instanceof GameMember && $member->getGame()->getSlug() === $slug) {
            $this->studio->removeMember($member);
        }

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/lead', name: 'lead', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function lead(string $slug, Request $request, UserRepository $users): Response
    {
        $game = $this->find($slug);
        $user = $users->find((int) $request->request->get('user'));

        if (!$this->canTransfer($game) || $user === null || !$user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $this->studio->transferLead($game, $user, $this->getUser());
        $this->addFlash('success', 'admin.studio.lead_changed');

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/review/approve', name: 'review_approve', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function approve(string $slug, ReviewManager $reviewer, GameReviewRepository $reviews): Response
    {
        $game = $this->leadOnly($slug);
        $review = $reviews->findPending($game) ?? throw $this->createNotFoundException();
        $error = $reviewer->approve($review, $this->getUser());
        $this->addFlash($error === null ? 'success' : 'error', $error ?? 'admin.studio.review.approved_flash');

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/review/reject', name: 'review_reject', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function reject(string $slug, Request $request, ReviewManager $reviewer, GameReviewRepository $reviews): Response
    {
        $game = $this->leadOnly($slug);
        $review = $reviews->findPending($game) ?? throw $this->createNotFoundException();
        $comment = trim((string) $request->request->get('comment'));

        if ($comment === '') {
            $this->addFlash('error', 'admin.studio.review.comment_required');
        } else {
            $reviewer->reject($review, $this->getUser(), $comment);
            $this->addFlash('success', 'admin.studio.review.rejected_flash');
        }

        return $this->redirectToRoute('admin_studio_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    #[Csrf('studio-{slug}')]
    public function delete(string $slug, Request $request): Response
    {
        $game = $this->find($slug);

        if (!$this->studio->isLead($game, $this->getUser()) || trim((string) $request->request->get('confirm')) !== $game->getName()) {
            return $this->redirectToRoute('admin_studio_show', [
                'slug' => $slug
            ]);
        }

        $this->studio->delete($game);

        return $this->redirectToRoute('admin_studio_index', [
            'scope' => 'all'
        ]);
    }


    protected function canTransfer(Game $game): bool
    {
        return $this->studio->isLead($game, $this->getUser()) || $this->get(AdminAccess::class)->can($this->getUser(), 'studio.team');
    }

    protected function leadOnly(string $slug): Game
    {
        $game = $this->find($slug);

        if (!$this->studio->isLead($game, $this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $game;
    }

    protected function find(string $slug): Game
    {
        return $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
    }
}