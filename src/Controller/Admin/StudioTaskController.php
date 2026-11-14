<?php

namespace App\Controller\Admin;

use App\Admin\Studio\Enum\GameTaskStatusEnum;
use App\Entity\Game;
use App\Entity\GameTask;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\GameTaskRepository;
use App\Repository\UserRepository;
use DateTime;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/studio', name: 'studio_task_')]
class StudioTaskController extends AbstractController
{
    public function __construct(
        protected GameRepository $games,
        protected GameTaskRepository $tasks,
        protected UserRepository $users,
    ) {
    }

    #[Route('/{slug}/tasks/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('studio-{slug}')]
    public function new(string $slug, Request $request): Response
    {
        $game = $this->game($slug);

        return $this->handle($request, (new GameTask())->setGame($game)->setAuthor($this->getUser()));
    }

    #[Route('/{slug}/tasks/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('studio-{slug}')]
    public function edit(string $slug, int $id, Request $request): Response
    {
        return $this->handle($request, $this->task($slug, $id));
    }

    #[Route('/{slug}/tasks/{id}/status', name: 'status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('studio-{slug}')]
    public function status(string $slug, int $id, Request $request): Response
    {
        $task = $this->task($slug, $id);
        $status = GameTaskStatusEnum::tryFrom((string) $request->request->get('status'));

        if ($status !== null) {
            $this->tasks->save($task->setStatus($status), true);
        }

        return $this->back($slug);
    }

    #[Route('/{slug}/tasks/{id}/check', name: 'check', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('studio-{slug}')]
    public function check(string $slug, int $id, Request $request): Response
    {
        $task = $this->task($slug, $id);
        $this->tasks->save($task->toggleItem((int) $request->request->get('item')), true);

        return $this->back($slug, $id);
    }

    #[Route('/{slug}/tasks/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('studio-{slug}')]
    public function delete(string $slug, int $id): Response
    {
        $this->tasks->delete($this->task($slug, $id), true);
        $this->addFlash('success', 'admin.tasks.flash.deleted');

        return $this->back($slug);
    }

    protected function handle(Request $request, GameTask $task): Response
    {
        $admins = $this->users->findAdmins();
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $ids = array_map('intval', (array) ($data['assignees'] ?? []));

            $task->setTitle(mb_substr(trim((string) ($data['title'] ?? '')), 0, 120))
                ->setDescription(trim((string) ($data['description'] ?? '')))
                ->setChecklistText((string) ($data['checklist'] ?? ''))
                ->setStatus(GameTaskStatusEnum::tryFrom((string) ($data['status'] ?? '')) ?? GameTaskStatusEnum::TODO)
                ->setStartAt($this->date($data['start_at'] ?? null))
                ->setEndAt($this->date($data['end_at'] ?? null))
                ->setAssignees(array_values(array_filter($admins, fn (User $admin): bool => in_array($admin->getId(), $ids, true))));

            $errors = array_filter([
                'title' => $task->getTitle() === '' ? 'admin.tasks.errors.title' : null,
                'end_at' => $task->getStartAt() && $task->getEndAt() && $task->getEndAt() < $task->getStartAt() ? 'admin.tasks.errors.dates' : null,
            ]);

            if ($errors === []) {
                $this->tasks->save($task, true);
                $this->addFlash('success', 'admin.flash.saved');

                return $this->back($task->getGame()->getSlug(), $task->getId());
            }
        }

        return $this->render('@admin/pages/studio/task.html.twig', [
            'game' => $task->getGame(),
            'task' => $task,
            'admins' => $admins,
            'statuses' => GameTaskStatusEnum::cases(),
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    protected function date(mixed $value): ?DateTime
    {
        $date = DateTime::createFromFormat('!Y-m-d', (string) $value);

        return $date === false ? null : $date;
    }

    protected function back(string $slug, ?int $id = null): Response
    {
        $url = $this->generateUrl('admin_studio_show', ['slug' => $slug]);

        return $this->redirect($url . ($id ? '?task=' . $id . '#task-' . $id : '#studio-tasks'));
    }

    protected function game(string $slug): Game
    {
        return $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
    }

    protected function task(string $slug, int $id): GameTask
    {
        $task = $this->tasks->find($id);

        return $task instanceof GameTask && $task->getGame()->getSlug() === $slug ? $task : throw $this->createNotFoundException();
    }
}
