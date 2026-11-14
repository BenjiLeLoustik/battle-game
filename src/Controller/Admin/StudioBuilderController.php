<?php

namespace App\Controller\Admin;

use App\Admin\Security\AdminAccess;
use App\Admin\Studio\Blueprint\BlueprintGenerator;
use App\Admin\Studio\Blueprint\BlueprintManager;
use App\Admin\Studio\Blueprint\BlueprintTemplates;
use App\Admin\Studio\EditorManager;
use App\Admin\Studio\ReviewManager;
use App\Admin\Studio\StudioManager;
use App\Entity\Game;
use App\Entity\StudioBlock;
use App\Repository\GameMemberRepository;
use App\Repository\GameRepository;
use App\Repository\StudioBlockRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Exception\AccessDeniedException;
use NeoPHP\Package\Yaml\YamlManager;

#[Route('/studio', name: 'studio_builder_')]
class StudioBuilderController extends AbstractController
{
    public function __construct(
        protected BlueprintManager $blueprints,
        protected GameRepository $games,
        protected GameMemberRepository $members,
        protected StudioManager $studio,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    #[Route('/{slug}/builder', name: 'index', methods: ['GET'])]
    public function index(string $slug, StudioBlockRepository $blocks): Response
    {
        $game = $this->find($slug);
        $labels = (new YamlManager())->parseFile($this->rootPath . '/translations/builder.fr.yaml');

        return $this->render('@admin/pages/studio/builder.html.twig', [
            'game' => $game,
            'blueprint' => $this->blueprints->load($game),
            'templates' => array_combine(BlueprintTemplates::NAMES, array_map(fn (string $name): array => $this->blueprints->normalize(BlueprintTemplates::get($name)), BlueprintTemplates::NAMES)),
            'labels' => $labels['builder'] ?? [],
            'schema' => [
                'elements' => BlueprintManager::ELEMENTS,
                'variants' => BlueprintManager::VARIANTS,
                'events' => BlueprintManager::EVENTS,
                'eventElements' => BlueprintManager::EVENT_ELEMENTS,
                'operands' => BlueprintManager::OPERANDS,
                'targets' => BlueprintManager::TARGETS,
                'ops' => BlueprintManager::OPS,
                'checks' => BlueprintManager::CHECKS,
                'actions' => BlueprintManager::ACTIONS,
                'sounds' => BlueprintManager::SOUNDS,
            ],
            'lead' => $this->isLead($game),
            'library' => array_map(fn (StudioBlock $block): array => $block->toArray(), $blocks->findOrdered()),
            'me' => $this->getUser()->getId(),
            'locked' => $this->studio->origin($game) === 'coded',
        ]);
    }

    #[Route('/{slug}/builder', name: 'save', methods: ['POST'])]
    #[Csrf('studio-builder-{slug}')]
    public function save(string $slug, Request $request): Response
    {
        $game = $this->editable($slug);

        return $this->json(['blueprint' => $this->blueprints->save($game, (array) ($request->toArray()['blueprint'] ?? []))]);
    }

    #[Route('/{slug}/builder/generate', name: 'generate', methods: ['POST'])]
    #[Csrf('studio-builder-{slug}')]
    public function generate(string $slug, Request $request, BlueprintGenerator $generator, EditorManager $editor, ReviewManager $reviews): Response
    {
        $game = $this->editable($slug);
        $data = $request->toArray();
        $publish = (bool) ($data['publish'] ?? false);

        if ($publish && !$this->isLead($game)) {
            throw new AccessDeniedException();
        }

        if ($this->studio->origin($game) !== 'nocode') {
            $this->backup($game);
        }

        $blueprint = $this->blueprints->save($game, (array) ($data['blueprint'] ?? []));
        $files = $generator->write($game, $blueprint);
        $error = $editor->validate($game);
        $version = null;

        if ($error === null && $publish) {
            $version = $editor->publish($game)->getVersion();
            $reviews->published($game, $this->getUser(), $version);
        }

        return $this->json(['blueprint' => $blueprint, 'files' => $files, 'error' => $error, 'version' => $version]);
    }

    #[Route('/{slug}/builder/blocks', name: 'block_save', methods: ['POST'])]
    #[Csrf('studio-builder-{slug}')]
    public function saveBlock(string $slug, Request $request, StudioBlockRepository $blocks): Response
    {
        $this->find($slug);
        $data = $request->toArray();
        $name = trim((string) ($data['name'] ?? ''));
        $definition = $this->blueprints->normalize(['variables' => $data['definition']['variables'] ?? [], 'elements' => $data['definition']['elements'] ?? [], 'rules' => $data['definition']['rules'] ?? []]);

        if ($name === '' || $definition['elements'] === []) {
            return $this->json(['message' => 'invalid'], 422);
        }

        $block = (new StudioBlock())->setName($name)->setAuthor($this->getUser())->setDefinition(['variables' => $definition['variables'], 'elements' => $definition['elements'], 'rules' => $definition['rules']]);
        $blocks->save($block, true);

        return $this->json(['block' => $block->toArray()]);
    }

    #[Route('/{slug}/builder/blocks/{id}/delete', name: 'block_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('studio-builder-{slug}')]
    public function deleteBlock(string $slug, int $id, StudioBlockRepository $blocks): Response
    {
        $this->find($slug);
        $block = $blocks->find($id) ?? throw $this->createNotFoundException();

        if ($block->getAuthor()?->getId() !== $this->getUser()->getId() && !$this->get(AdminAccess::class)->can($this->getUser(), 'studio.team')) {
            throw new AccessDeniedException();
        }

        $blocks->delete($block, true);

        return $this->json(['ok' => true]);
    }

    protected function editable(string $slug): Game
    {
        $game = $this->find($slug);

        if ($this->studio->origin($game) === 'coded') {
            throw new AccessDeniedException();
        }

        return $game;
    }

        protected function backup(Game $game): void
    {
        $root = $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory());
        $target = $root . '/backup/' . date('Ymd-His');

        foreach (['src', 'assets', 'translations', 'game.json'] as $entry) {
            $source = $root . '/' . $entry;

            if (is_file($source)) {
                $this->copy($source, $target . '/' . $entry);
            }

            if (is_dir($source)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));

                foreach ($files as $file) {
                    $this->copy($file->getPathname(), $target . '/' . $entry . substr($file->getPathname(), strlen($source)));
                }
            }
        }
    }

    protected function copy(string $from, string $to): void
    {
        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0777, true);
        }

        copy($from, $to);
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
