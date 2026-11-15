<?php

namespace App\Controller\Admin;

use App\Entity\LevelDefinition;
use App\Repository\BadgeRepository;
use App\Repository\GameResultRepository;
use App\Repository\LevelDefinitionRepository;
use App\Shared\User\LevelManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/levels', name: 'level_')]
class LevelController extends AbstractAdminController
{
    public function __construct(
        protected LevelDefinitionRepository $levels,
        protected BadgeRepository $badges,
        protected GameResultRepository $results,
        protected LevelManager $levelManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $playerCounts = [];

        foreach ($this->results->playerExperiences() as $row) {
            $number = $this->levelManager->level((int) $row['experience']);
            $playerCounts[$number] = ($playerCounts[$number] ?? 0) + 1;
        }

        return $this->render('@admin/pages/level/index.html.twig', [
            'levels' => $this->levels->findAll(['number' => 'ASC']),
            'playerCounts' => $playerCounts,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-levels')]
    public function new(Request $request): Response
    {
        $all = $this->levels->findAll(['number' => 'ASC']);
        $last = $all === [] ? null : $all[count($all) - 1];

        $level = (new LevelDefinition())
            ->setNumber($last === null ? 1 : $last->getNumber() + 1)
            ->setRequiredExperience($last === null ? 0 : $last->getRequiredExperience() + 20);

        return $this->handle($request, $level);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-levels')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle(
            $request,
            $this->levels->find($id) ?? throw $this->createNotFoundException(),
        );
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-levels')]
    public function delete(int $id): Response
    {
        $level = $this->levels->find($id) ?? throw $this->createNotFoundException();
        $all = $this->levels->findAll(['number' => 'ASC']);
        $last = $all === [] ? null : $all[count($all) - 1];

        if ($last === null || $last->getId() !== $level->getId()) {
            $this->addFlash('error', 'admin.levels.delete_last');

            return $this->redirectToRoute('admin_level_index');
        }

        $this->deleteTranslations($level);
        $this->levels->delete($level, true);
        $this->audit('level.delete', (string) $level->getNumber());

        return $this->done('admin_level_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, LevelDefinition $level): Response
    {
        $errors = [];
        $badges = $this->badges->findAll(['name' => 'ASC']);

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $number = filter_var($data['number'] ?? null, FILTER_VALIDATE_INT);
            $experience = filter_var($data['required_experience'] ?? null, FILTER_VALIDATE_INT);
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);
            $badgeId = (int) ($data['badge'] ?? 0);
            $badge = $badgeId > 0 ? $this->badges->find($badgeId) : null;

            if ($number === false || $number < 1) {
                $errors['number'] = 'admin.levels.errors.number';
            }

            if ($experience === false || $experience < 0) {
                $errors['required_experience'] = 'admin.levels.errors.experience';
            }

            if ($badgeId > 0 && $badge === null) {
                $errors['badge'] = 'admin.levels.errors.badge';
            }

            if ($errors === []) {
                $rows = [];

                foreach ($this->levels->findAll(['number' => 'ASC']) as $other) {
                    if ($other->getId() !== $level->getId()) {
                        $rows[$other->getNumber()] = $other->getRequiredExperience();
                    }
                }

                $rows[$number] = $experience;
                ksort($rows);

                $expected = 1;
                $previousExperience = -1;

                foreach ($rows as $rowNumber => $rowExperience) {
                    if ($rowNumber !== $expected || $rowExperience <= $previousExperience) {
                        $errors['required_experience'] = 'admin.levels.errors.order';
                        break;
                    }

                    $expected++;
                    $previousExperience = $rowExperience;
                }

                if (($rows[1] ?? null) !== 0) {
                    $errors['required_experience'] = 'admin.levels.errors.first';
                }
            }

            if ($errors === []) {
                $this->levels->save($level
                    ->setNumber((int) $number)
                    ->setRequiredExperience((int) $experience)
                    ->setName($name)
                    ->setBadge($badge), true);
                $this->saveTranslations($level, $request);
                $this->audit('level.save', (string) $number);

                return $this->done('admin_level_index');
            }

            $level->setNumber((int) ($number ?: 1))
                ->setRequiredExperience((int) ($experience ?: 0))
                ->setName($name)
                ->setBadge($badge);
        }

        return $this->render('@admin/pages/level/form.html.twig', [
            'level' => $level,
            'levels' => $this->levels->findAll(['number' => 'ASC']),
            'badges' => $badges,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }
}