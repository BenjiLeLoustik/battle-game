<?php

namespace App\Admin\Studio;

use App\Entity\Game;
use App\Entity\GameMember;
use App\Entity\User;
use App\Repository\GameMemberRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Game\GameManager;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use NeoPHP\Component\Container\Attribute\Autowire;

class StudioManager
{
    private const SKELETON = __DIR__ . '/Skeleton';
    private const TRASH = '.trash';

    public function __construct(
        protected GameManager $games,
        protected GameRepository $repository,
        protected GameMemberRepository $members,
        protected UserRepository $users,
        protected NotificationManager $notifications,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function list(User $user, bool $mine, ?GameStatusEnum $status, string $search): array
    {
        $teams = $this->members->teams();

        return array_values(array_filter(
            $this->repository->findBy([], ['name' => 'ASC']),
            fn (Game $game): bool => (!$mine || $this->isMember($teams[$game->getId()] ?? [], $user))
                && ($status === null || $game->getStatus() === $status)
                && ($search === '' || str_contains(mb_strtolower($game->getName()), mb_strtolower($search))),
        ));
    }

    public function create(NewGameRequest $request, User $user): Game
    {
        $directory = $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . $request->getDirectory();
        $namespace = 'BattleGame\\' . $request->getDirectory() . '\\Sdk';
        $replacements = ['{{namespace}}' => $namespace, '{{class}}' => $request->getDirectory(), '{{block}}' => $request->slug];

        $this->write($directory . '/game.json', $this->manifest($request, $namespace, $user));
        $this->write($directory . '/composer.json', $this->json([
            'name' => 'battle-game/' . $request->slug,
            'autoload' => ['psr-4' => [$namespace . '\\' => 'src/']],
        ]));

        foreach (['Manager.php', 'Server.php'] as $file) {
            $this->write($directory . '/src/' . $request->getDirectory() . $file, $this->skeleton($file, $replacements));
        }

        $this->write($directory . '/assets/js/client.js', $this->skeleton('client.js', $replacements));
        $this->write($directory . '/assets/css/client.css', $this->skeleton('client.css', $replacements));
        $this->write($directory . '/translations/game.fr.yaml', $this->skeleton('game.fr.yaml', $replacements));

        $game = $this->games->install($request->getDirectory(), GameStatusEnum::DEV);
        $this->addMember($game, $user, GameMemberRoleEnum::LEAD);

        foreach ($this->users->findAdmins() as $admin) {
            if ($admin->getId() !== $user->getId() && in_array($admin->getId(), $request->team, true)) {
                $this->addMember($game, $admin, GameMemberRoleEnum::DEVELOPER);
            }
        }

        return $game;
    }

    public function changeStatus(Game $game, GameStatusEnum $status, User $user): void
    {
        if ($game->getStatus() !== $status) {
            $released = $status === GameStatusEnum::LIVE && $game->getStatus() !== GameStatusEnum::MAINTENANCE;
            $this->repository->save($game->setStatus($status, $user), true);

            if ($released) {
                $this->notifications->broadcast(NotificationTypeEnum::GAME_RELEASED, ['game' => $game->getName(), 'slug' => $game->getSlug()]);
            }
        }
    }

    public function addMember(Game $game, User $user, GameMemberRoleEnum $role): void
    {
        if ($this->members->findOneBy(['game' => $game, 'user' => $user]) === null) {
            $this->members->save((new GameMember())->setGame($game)->setUser($user)->setRole($role), true);
        }
    }

    public function removeMember(GameMember $member): void
    {
        if (!$member->isLead()) {
            $this->members->delete($member, true);
        }
    }

    public function candidates(Game $game): array
    {
        $taken = array_map(fn (GameMember $member): int => $member->getUser()->getId(), $this->members->findBy(['game' => $game]));

        return array_values(array_filter($this->users->findAdmins(), fn (User $admin): bool => !in_array($admin->getId(), $taken, true)));
    }

    public function origin(Game $game): string
    {
        $root = $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory());
        $manifest = is_file($root . '/game.json') ? json_decode((string) file_get_contents($root . '/game.json'), true) : null;

        if (!is_array($manifest)) {
            return 'coded';
        }

        if (($manifest['nocode'] ?? false) === true) {
            return 'nocode';
        }

        $manager = (string) ($manifest['manager'] ?? '');
        $class = basename($game->getDirectory());
        $replacements = ['{{namespace}}' => substr($manager, 0, (int) strrpos($manager, '\\')), '{{class}}' => $class, '{{block}}' => $game->getSlug()];
        $files = ['src/' . $class . 'Manager.php' => 'Manager.php', 'src/' . $class . 'Server.php' => 'Server.php', 'assets/js/client.js' => 'client.js'];

        foreach ($files as $path => $skeleton) {
            $current = is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : null;

            if ($current === null || $this->normalize($current) !== $this->normalize($this->skeleton($skeleton, $replacements))) {
                return 'coded';
            }
        }

        return 'fresh';
    }

    public function transferLead(Game $game, User $user, User $by): void
    {
        foreach ($this->members->findBy(['game' => $game]) as $member) {
            if ($member->isLead() && $member->getUser()->getId() !== $user->getId()) {
                $this->members->save($member->setRole(GameMemberRoleEnum::DEVELOPER));
            }
        }

        $member = $this->members->findOneBy(['game' => $game, 'user' => $user]) ?? (new GameMember())->setGame($game)->setUser($user);
        $this->members->save($member->setRole(GameMemberRoleEnum::LEAD), true);

        if ($user->getId() !== $by->getId()) {
            $this->notifications->notify($user, NotificationTypeEnum::STUDIO_LEAD, ['name' => $by->getUsername(), 'game' => $game->getName(), 'slug' => $game->getSlug(), 'initials' => $by->getInitials(), 'avatar' => $by->getAvatar()]);
        }
    }

    public function isLead(Game $game, User $user): bool
    {
        return $this->members->findOneBy([
            'game' => $game,
            'user' => $user
        ])?->isLead() ?? false;
    }

    public function delete(Game $game): void
    {
        $games = $this->rootPath . '/' . GameManager::GAMES_DIRECTORY;
        $source = $games . '/' . basename($game->getDirectory());

        if (is_dir($source)) {
            if (!is_dir($games . '/' . self::TRASH)) {
                mkdir($games . '/' . self::TRASH, 0777, true);
            }

            rename($source, $games . '/' . self::TRASH . '/' . basename($game->getDirectory()) . '-' . date('YmdHis'));
        }

        $this->games->removeAssets($game->getSlug());
        $this->repository->delete($game, true);
    }

    protected function manifest(NewGameRequest $request, string $namespace, User $user): string
    {
        return $this->json([
            'slug' => $request->slug,
            'name' => $request->name,
            'version' => '0.1.0',
            'author' => $user->getUsername(),
            'sdk' => '^1.0',
            'description' => $request->description,
            'category' => $request->category,
            'players' => ['min' => $request->minPlayers, 'max' => $request->maxPlayers],
            'duration' => 3,
            'solo' => $request->minPlayers === 1,
            'points' => [10, 5, 2],
            'colors' => ['primary' => '#2EE6D6', 'secondary' => '#7C5CFF'],
            'manager' => $namespace . '\\' . $request->getDirectory() . 'Manager',
            'server' => $namespace . '\\' . $request->getDirectory() . 'Server',
            'client' => 'js/client.js',
            'style' => 'css/client.css',
            'settings' => [['name' => 'target', 'type' => 'choice', 'choices' => [10, 20, 30], 'default' => 10]],
            'rules' => [['title' => 'Clique', 'description' => 'Chaque clic te rapporte un point. Le premier qui atteint l\'objectif gagne.']],
            'stats' => [['key' => 'clicks', 'label' => 'Clics', 'aggregate' => 'sum', 'format' => '{value}']],
        ]);
    }

    protected function normalize(string $content): string
    {
        return trim(str_replace("\r\n", "\n", $content));
    }

    protected function skeleton(string $file, array $replacements): string
    {
        return strtr((string) file_get_contents(self::SKELETON . '/' . $file . '.dist'), $replacements);
    }

    protected function write(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    protected function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    protected function isMember(array $team, User $user): bool
    {
        return array_filter($team, fn (GameMember $member): bool => $member->getUser()->getId() === $user->getId()) !== [];
    }
}