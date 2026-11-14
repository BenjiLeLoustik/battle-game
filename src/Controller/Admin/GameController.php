<?php

namespace App\Controller\Admin;

use App\Admin\Game\GitInstaller;
use App\Entity\Game;
use App\Repository\CategoryRepository;
use App\Shared\Game\GameManager;
use FilesystemIterator;
use NeoPHP\Component\Container\Attribute\Autowire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use App\Repository\GameRepository;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Component\Session\SessionManagerInterface;

#[Route('/games', name: 'game_')]
class GameController extends AbstractAdminController
{
    private const ANALYSIS = '_battlegame.admin_game_analysis';
    private const SOURCES = ['git', 'local'];
    private const SKIP = ['.git', 'node_modules', '.idea', '.vscode'];

    public function __construct(
        protected GameRepository $games,
        protected GitInstaller $installer,
        protected SessionManagerInterface $session,
        protected CategoryRepository $categories,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = $this->filters($request->query->all());
        $games = $this->filter($filters);

        return $this->render('@admin/pages/game/index.html.twig', [
            'games' => $games,
            'total' => $this->games->count(),
            'featured' => $this->games->findFeatured(),
            'analysis' => $this->session->get(self::ANALYSIS),
            'filters' => $filters,
            'filtered' => $filters['q'] !== '' || $filters['status'] !== null || $filters['category'] !== '' || $filters['source'] !== '' || $filters['early'],
            'statuses' => GameStatusEnum::cases(),
            'categories' => $this->categories->findAll(['name' => 'ASC']),
            'sources' => self::SOURCES,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function export(Request $request): Response
    {
        $this->allow('games.install');
        $data = $request->request->all();
        $selected = array_map('strval', (array) ($data['games'] ?? []));
        $games = ($data['all'] ?? '') === '1'
            ? $this->filter($this->filters($data))
            : array_values(array_filter(array_map(fn (string $slug): ?Game => $this->games->findOneBy(['slug' => $slug]), $selected)));

        if ($games === []) {
            $this->addFlash('error', 'admin.games.export.empty');

            return $this->redirectToRoute('admin_game_index');
        }

        $file = tempnam(sys_get_temp_dir(), 'bg-export-');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);

        foreach ($games as $game) {
            $this->zipDirectory($zip, $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory()), basename($game->getDirectory()));
        }

        $zip->addFromString('games.json', json_encode(array_map(fn (Game $game): array => [
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
            'version' => $game->getVersion(),
            'directory' => basename($game->getDirectory()),
            'status' => $game->getStatus()->value,
            'repository' => $game->getRepository(),
        ], $games), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();
        $this->audit('game.export', implode(', ', array_map(fn (Game $game): string => $game->getName(), $games)));
        $name = count($games) === 1 ? $games[0]->getSlug() . '-' . $games[0]->getVersion() : 'battlegame-jeux-' . count($games);

        return $this->fileResponse($file, $name . '-' . date('Ymd-Hi') . '.zip')->deleteFileAfterSend();
    }

    #[Route('/analyze', name: 'analyze', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function analyze(Request $request): Response
    {
        $url = trim((string) $request->request->get('url'));
        $ref = trim((string) $request->request->get('ref')) ?: 'main';
        $result = $this->installer->analyze($url, $ref, trim((string) $request->request->get('token')));
        $this->session->set(self::ANALYSIS, $result + ['url' => $url, 'ref' => $ref, 'activate' => $request->request->get('activate') === '1']);

        return $this->redirectToRoute('admin_game_index');
    }

    #[Route('/install', name: 'install', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function install(): Response
    {
        $analysis = $this->session->remove(self::ANALYSIS);

        if (!is_array($analysis) || $analysis['id'] === null) {
            return $this->redirectToRoute('admin_game_index');
        }

        $game = $this->installer->install($analysis['id'], $analysis['url'], $analysis['ref'], $analysis['activate'] ? GameStatusEnum::LIVE : GameStatusEnum::TEST);

        if ($game === null) {
            $this->addFlash('error', 'admin.games.install_failed');

            return $this->redirectToRoute('admin_game_index');
        }

        $this->audit('game.install', $game->getName() . ' ' . $game->getVersion());

        return $this->done('admin_game_index', [], 'admin.flash.installed');
    }

    #[Route('/{slug}/update', name: 'update', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function update(string $slug): Response
    {
        $game = $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $error = $this->installer->update($game);

        if ($error !== null) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('admin_game_index');
        }

        $this->audit('game.update', $game->getName() . ' ' . $game->getVersion());

        return $this->done('admin_game_index');
    }

    #[Route('/{slug}/toggle', name: 'toggle', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function toggle(string $slug, NotificationManager $notifications): Response
    {
        $this->allow('games.state');
        $game = $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $status = $game->getStatus() === GameStatusEnum::LIVE ? GameStatusEnum::ARCHIVED : GameStatusEnum::LIVE;
        $this->games->save($game->setStatus($status, $this->getUser()), true);
        $this->audit('game.status', $game->getName() . ' → ' . $status->translate());

        if ($status === GameStatusEnum::LIVE) {
            $notifications->broadcast(NotificationTypeEnum::GAME_RELEASED, ['game' => $game->getName(), 'slug' => $game->getSlug()]);
        }

        return $this->done('admin_game_index');
    }

    protected function filters(array $data): array
    {
        return [
            'q' => mb_substr(trim((string) ($data['q'] ?? '')), 0, 60),
            'status' => GameStatusEnum::tryFrom((string) ($data['status'] ?? '')),
            'category' => (string) ($data['category'] ?? ''),
            'source' => in_array($data['source'] ?? '', self::SOURCES, true) ? (string) $data['source'] : '',
            'early' => ($data['early'] ?? '') === '1',
        ];
    }

    protected function filter(array $filters): array
    {
        $query = mb_strtolower($filters['q']);

        return array_values(array_filter($this->games->findAll(['name' => 'ASC']), fn (Game $game): bool => ($query === '' || str_contains(mb_strtolower($game->getName() . ' ' . $game->getSlug() . ' ' . $game->getDirectory() . ' ' . ($game->getCategory()?->getName() ?? '')), $query))
            && ($filters['status'] === null || $game->getStatus() === $filters['status'])
            && ($filters['category'] === '' || $game->getCategory()?->getSlug() === $filters['category'])
            && ($filters['source'] === '' || ($filters['source'] === 'git') === ($game->getRepository() !== null))
            && (!$filters['early'] || $game->isEarlyAccess())));
    }

    protected function zipDirectory(ZipArchive $zip, string $directory, string $prefix): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        $base = strlen(realpath($directory)) + 1;

        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getRealPath(), $base));

            if ($file->isFile() && array_intersect(explode('/', $relative), self::SKIP) === []) {
                $zip->addFile($file->getRealPath(), $prefix . '/' . $relative);
            }
        }
    }

    #[Route('/{slug}/early', name: 'early', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function early(string $slug): Response
    {
        $this->allow('games.state');
        $game = $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $this->games->save($game->setEarlyAccess(!$game->isEarlyAccess()), true);
        $this->audit('game.early', $game->getName() . ($game->isEarlyAccess() ? ' +' : ' -'));

        return $this->done('admin_game_index');
    }

    #[Route('/{slug}/feature', name: 'feature', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function feature(string $slug): Response
    {
        $this->allow('games.state');
        $game = $this->games->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $featured = array_filter($this->games->findFeatured(), fn ($other): bool => $other->getId() !== $game->getId());

        if ($game->isFeatured()) {
            $game->setFeaturedPosition(null);
            $this->games->save($game);
        } else {
            $featured[] = $game;
        }

        $this->games->sortFeatured($featured);
        $this->audit('game.feature', $game->getName() . ($game->isFeatured() ? ' +' : ' -'));

        return $this->done('admin_game_index');
    }

    #[Route('/{slug}/feature/{direction}', name: 'feature_move', methods: ['POST'], requirements: ['direction' => 'up|down'])]
    #[Csrf('admin-games')]
    public function moveFeatured(string $slug, string $direction): Response
    {
        $this->allow('games.state');
        $featured = $this->games->findFeatured();
        $index = array_search($slug, array_map(fn ($game): string => $game->getSlug(), $featured), true);
        $target = $index === false ? false : $index + ($direction === 'up' ? -1 : 1);

        if ($target !== false && isset($featured[$target])) {
            [$featured[$index], $featured[$target]] = [$featured[$target], $featured[$index]];
            $this->games->sortFeatured($featured);
        }

        return $this->redirectToRoute('admin_game_index');
    }

    #[Route('/analysis/clear', name: 'clear', methods: ['POST'])]
    #[Csrf('admin-games')]
    public function clear(): Response
    {
        $this->session->remove(self::ANALYSIS);

        return $this->redirectToRoute('admin_game_index');
    }
}
