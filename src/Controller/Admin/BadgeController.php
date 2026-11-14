<?php

namespace App\Controller\Admin;

use App\Admin\Studio\NewGameRequest;
use App\Entity\Badge;
use App\Repository\BadgeRepository;
use App\Repository\GameRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use App\Shared\Badge\BadgeDesign;
use App\Shared\Badge\BadgeManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/badges', name: 'badge_')]
class BadgeController extends AbstractAdminController
{
    public function __construct(
        protected BadgeRepository $badges,
        protected BadgeManager $manager,
        protected GameRepository $games,
        protected UserBadgeRepository $owners,
        protected UserRepository $users
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $game = (string) $request->query->get('game');
        $rarity = in_array($request->query->get('rarity'), BadgeDesign::RARITIES, true) ? $request->query->get('rarity') : '';
        $search = mb_strtolower(trim((string) $request->query->get('q')));
        $members = max(1, $this->users->count(['guest' => false]));
        $all = $this->badges->findAll(['position' => 'ASC', 'id' => 'ASC']);
        $rows = array_filter($all, fn (Badge $badge): bool => ($game === '' || ($game === 'general' ? $badge->getGame() === null : $badge->getGame()?->getSlug() === $game))
            && ($rarity === '' || ($badge->getDesign()['rarity'] ?? 'common') === $rarity)
            && ($search === '' || str_contains(mb_strtolower($badge->getName() . ' ' . $badge->getCode()), $search)));
        $usage = [];

        foreach ($this->manager->rules() as $name => $rule) {
            $usage[$name] = ['rule' => $rule, 'count' => count(array_filter($all, fn (Badge $badge): bool => $badge->getRule() === $name))];
        }

        return $this->render('@admin/pages/badge/index.html.twig', [
            'rows' => array_map(fn (Badge $badge): array => [
                'badge' => $badge,
                'view' => $this->manager->view($badge),
                'rate' => round($this->owners->count(['badge' => $badge]) / $members * 100, 1),
            ], $rows),
            'kpis' => [
                'total' => count($all),
                'general' => count(array_filter($all, fn (Badge $badge): bool => $badge->getGame() === null)),
                'games' => count(array_filter($all, fn (Badge $badge): bool => $badge->getGame() !== null)),
                'awarded' => $this->owners->count(),
            ],
            'games' => $this->games->findAll(['name' => 'ASC']),
            'filters' => ['game' => $game, 'rarity' => $rarity, 'q' => $search],
            'rarities' => BadgeDesign::RARITIES,
            'usage' => $usage,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-badges')]
    public function new(Request $request): Response
    {
        $source = $this->badges->find((int) $request->query->get('from'));
        $badge = (new Badge())->setRule('games_played')->setParams(['count' => 1])->setDesign(['rarity' => 'common', 'shape' => 'hexagon', 'icon' => 'star']);

        if ($source !== null) {
            $badge->setName($source->getName())->setDescription($source->getDescription())->setGame($source->getGame())->setRule($source->getRule())->setParams($source->getParams())->setDesign($source->getDesign())->setSecret($source->isSecret());
        }

        return $this->handle($request, $badge);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-badges')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-badges')]
    public function toggle(int $id): Response
    {
        $badge = $this->find($id);
        $this->badges->save($badge->setActive(!$badge->isActive()), true);
        $this->audit('badge.save', $badge->getCode());

        return $this->redirectToRoute('admin_badge_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-badges')]
    public function delete(int $id): Response
    {
        $badge = $this->find($id);

        if ($this->owners->count(['badge' => $badge]) > 0) {
            $this->addFlash('error', 'admin.badges.owned');

            return $this->redirectToRoute('admin_badge_edit', ['id' => $id]);
        }

        $this->deleteTranslations($badge);
        $this->badges->delete($badge, true);
        $this->audit('badge.delete', $badge->getCode());

        return $this->done('admin_badge_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, Badge $badge): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $game = ($data['scope'] ?? 'general') === 'game' ? $this->games->find((int) ($data['game'] ?? 0)) : null;
            $params = json_decode((string) ($data['params'] ?? '{}'), true);
            $code = ($game?->getSlug() ?? 'general') . '.' . str_replace('-', '_', NewGameRequest::slugify((string) ($data['code'] ?? '') ?: (string) ($data['name'] ?? '')));
            $other = $this->badges->findOneBy(['code' => $code]);
            $errors = array_filter([
                'name' => trim((string) ($data['name'] ?? '')) === '' ? 'admin.badges.name_required' : null,
                'code' => $other !== null && $other !== $badge ? 'admin.badges.code_taken' : null,
                'game' => ($data['scope'] ?? '') === 'game' && $game === null ? 'admin.badges.game_required' : null,
                'rule' => $this->manager->rule((string) ($data['rule'] ?? '')) === null ? 'admin.badges.rule_invalid' : null,
                'params' => !is_array($params) ? 'admin.badges.params_invalid' : null,
            ]);

            if ($errors === []) {
                $this->badges->save($badge
                    ->setCode($code)
                    ->setGame($game)
                    ->setName(mb_substr(trim($data['name']), 0, 80))
                    ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 160))
                    ->setRule((string) $data['rule'])
                    ->setParams($params)
                    ->setDesign($this->design((array) ($data['design'] ?? [])))
                    ->setActive(isset($data['active']))
                    ->setSecret(isset($data['secret'])), true);
                $this->saveTranslations($badge, $request);
                $this->audit('badge.save', $code);

                if (isset($data['award'])) {
                    $this->award($badge);
                }

                return $this->done('admin_badge_index');
            }
        }

        return $this->render('@admin/pages/badge/form.html.twig', [
            'badge' => $badge,
            'errors' => $errors,
            'games' => $this->games->findAll(['name' => 'ASC']),
            'rules' => $this->manager->rules(),
            'design' => BadgeDesign::normalize($badge->getDesign(), $badge->getGame()?->getColor()),
            'shapes' => BadgeDesign::SHAPES,
            'rarities' => BadgeDesign::RARITIES,
            'frames' => BadgeDesign::FRAMES,
            'icons' => BadgeDesign::ICONS,
            'owners' => $badge->getId() === null ? 0 : $this->owners->count(['badge' => $badge]),
        ], $errors === [] ? 200 : 422);
    }

    protected function design(array $data): array
    {
        $design = BadgeDesign::normalize($data);

        return [
            'shape' => $design['shape'],
            'rarity' => $design['rarity'],
            'icon' => $design['icon'],
            'color' => isset($data['color_enabled']) ? $design['color'] : null,
            'glow' => $design['glow'],
            'label' => $design['label'],
            'frame' => in_array($data['frame'] ?? '', BadgeDesign::FRAMES, true) ? $data['frame'] : 'auto',
        ];
    }

    protected function award(Badge $badge): void
    {
        $users = $this->owners->getOrm()->getConnection()->fetchFirstColumn(
            $badge->getGame() === null ? 'SELECT DISTINCT user_id FROM game_result' : 'SELECT DISTINCT user_id FROM game_result WHERE game_id = :game',
            $badge->getGame() === null ? [] : ['game' => $badge->getGame()->getId()],
        );

        foreach ($users as $id) {
            $user = $this->users->find((int) $id);

            if ($user !== null) {
                $this->manager->evaluate($user, $badge->getGame() === null ? [] : [$badge->getGame()]);
            }
        }
    }

    protected function find(int $id): Badge
    {
        return $this->badges->find($id) ?? throw $this->createNotFoundException();
    }
}
