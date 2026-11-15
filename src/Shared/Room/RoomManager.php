<?php

namespace App\Shared\Room;

use App\Entity\Game;
use App\Entity\Room;
use App\Entity\RoomMember;
use App\Entity\RoomMessage;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\RoomMemberRepository;
use App\Repository\RoomMessageRepository;
use App\Repository\RoomRepository;
use App\Sdk\Trait\ClockTrait;
use App\Shared\Game\Enum\GameMatchStatusEnum;
use App\Shared\Game\GameManager;
use App\Shared\Game\GameMatchManager;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Moderation\ModerationManager;
use App\Shared\Room\Enum\RoomStatusEnum;
use App\Shared\Room\Exception\RoomActionException;
use App\Shared\Room\Exception\RoomFullException;
use App\Shared\Room\Exception\RoomGuestsException;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\ShopManager;
use App\Shared\Shop\SubscriptionManager;
use App\Shared\Translation\Translator;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;

class RoomManager
{
    use ClockTrait;

    public const MIN_PLAYERS = 2;
    public const MESSAGES = 50;
    private const LAUNCH_DELAY = 5000;
    private const CONFIGURE_DELAY = 45000;
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        protected RoomRepository $rooms,
        protected RoomMemberRepository $members,
        protected RoomMessageRepository $messages,
        protected GameRepository $games,
        protected GameMatchManager $matches,
        protected GameManager $gameManager,
        protected MaintenanceManager $maintenance,
        protected MercureManagerInterface $mercure,
        protected ModerationManager $moderation,
        protected SettingManager $settings,
        protected LifeManager $lives,
        protected SubscriptionManager $subscriptions,
        protected ShopManager $shop,
    ) {
    }

    public function create(Room $room, User $host): Room
    {
        $official = $room->isOfficial();

        if (!$official) {
            $this->lives->assert($host);
        }

        $room->setCode($this->code())
            ->setHost($official ? null : $host)
            ->setPermanent($room->isPermanent() || $official);

        $this->rooms->save($room, true);

        if (!$official) {
            $this->members->save($this->member($room, $host, false), true);
        }

        $this->system($room, 'site.room.system.created', [
            'name' => $official ? 'BattleGame' : $host->getUsername(),
        ]);

        return $room;
    }

    public static function normalize(string $code): string
    {
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));

        return strlen($code) === 6 ? substr($code, 0, 3) . '-' . substr($code, 3) : $code;
    }

    public function find(string $code): ?Room
    {
        return $this->rooms->findByCode(self::normalize($code));
    }

    public function isMember(Room $room, User $user): bool
    {
        return $this->members->findMember($room, $user) !== null;
    }

    public function join(Room $room, User $user): RoomMember
    {
        $member = $this->members->findMember($room, $user);

        if ($member === null) {
            if ($user->isGuest() && !$room->allowsGuests()) {
                throw new RoomGuestsException();
            }

            $full = count($this->players($room)) >= $room->getMaxPlayers();

            if (!$full && !$this->lives->canPlay($user)) {
                if (!$room->allowsSpectators()) {
                    $this->lives->assert($user);
                }

                $full = true;
            }

            if ($full && !$room->allowsSpectators()) {
                throw new RoomFullException();
            }

            $member = $this->member($room, $user, $full);
            $this->members->save($member, true);
            $this->system($room, $full ? 'site.room.system.watching' : 'site.room.system.joined', ['name' => $user->getUsername()]);
        }

        $this->touch($room, $member);

        return $member;
    }

    public function ping(Room $room, User $user): void
    {
        $member = $this->members->findMember($room, $user) ?? throw new RoomActionException();
        $this->touch($room, $member);
    }

    public function close(Room $room, string $message): void
    {
        $this->mercure->publish(Update::json($this->topic($room), ['type' => 'closed', 'message' => $message]));
        $this->rooms->delete($room, true);
    }

    public function kick(Room $room, User $user): void
    {
        $member = $this->members->findMember($room, $user);

        if ($member === null) {
            return;
        }

        $this->members->delete($member, true);
        $this->mercure->publish(Update::json($this->topic($room), ['type' => 'kicked', 'user' => (string) $user->getId()]));
        $this->system($room, 'site.room.system.kicked', ['name' => $user->getUsername()]);
        $this->publish($room);
    }

    public function members(Room $room): array
    {
        return $this->members->findByRoom($room);
    }

    public function leave(Room $room, User $user): void
    {
        $member = $this->members->findMember($room, $user);

        if ($member === null) {
            return;
        }

        $this->members->delete($member, true);
        $remaining = $this->members->findByRoom($room);

        if ($remaining === [] && !$room->isPermanent() && !$room->isOfficial()) {
            $this->rooms->delete($room, true);

            return;
        }

        if ($room->isHost($user)) {
            $room->setHost(($remaining[0] ?? null)?->getUser());
            $this->rooms->save($room, true);
        }

        $this->system($room, 'site.room.system.left', ['name' => $user->getUsername()]);
        $this->publish($room);
    }

    public function vote(Room $room, User $user, int $game): void
    {
        $round = $room->getRound();
        $member = $this->members->findMember($room, $user);

        if ($room->getStatus() !== RoomStatusEnum::VOTING || $member === null || $member->isSpectator() || !in_array($game, $round['options'] ?? [], true)) {
            throw new RoomActionException();
        }

        $round['votes'][(string) $user->getId()] = $game;
        $room->setRound($round);
        $this->advance($room, true);
    }

    public function configure(Room $room, User $user, array $values, bool $defaults): void
    {
        $game = $this->games->find((int) ($room->getRound()['winner'] ?? 0));

        if (!$room->isHost($user) || $room->getStatus() !== RoomStatusEnum::CONFIGURING || $game === null) {
            throw new RoomActionException();
        }

        $this->applyRules($room, $game, $defaults ? [] : $values);
        $this->system($room, $defaults ? 'site.room.system.rules_default' : 'site.room.system.rules', ['name' => $user->getUsername(), 'game' => $game->getName()]);
        $this->persist($room);
    }

    public function launchNow(Room $room, User $user): void
    {
        if (!$room->isHost($user) || $room->getStatus() !== RoomStatusEnum::LAUNCHING) {
            throw new RoomActionException();
        }

        $this->launch($room);
        $this->persist($room);
    }

    public function say(Room $room, User $user, string $text): void
    {
        $text = $this->settings->filter(mb_substr(trim($text), 0, RoomMessage::MAX_LENGTH));

        if ($text === '' || !$this->isMember($room, $user) || $this->moderation->isMuted($user)) {
            throw new RoomActionException();
        }

        $this->post($room, $user, $text);
    }

    public function advance(Room $room, bool $changed = false): void
    {
        if ($room->getStatus() === RoomStatusEnum::PLAYING && $this->finishMatch($room)) {
            $changed = true;
        }

        $players = $this->players($room);

        if ($room->getStatus() === RoomStatusEnum::WAITING && count($players) >= self::MIN_PLAYERS && ($room->getRound()['blockedAt'] ?? 0) < $this->now() - 10000) {
            $this->startRound($room, count($players));
            $changed = true;
        }

        if ($room->getStatus() === RoomStatusEnum::VOTING && $this->voteIsOver($room, $players)) {
            $this->resolve($room);
            $changed = true;
        }

        if ($room->getStatus() === RoomStatusEnum::CONFIGURING && $this->now() >= ($room->getRound()['configureEndsAt'] ?? 0)) {
            $game = $this->games->find((int) ($room->getRound()['winner'] ?? 0));

            if ($game === null) {
                $room->setStatus(RoomStatusEnum::WAITING)->resetRound();
            } else {
                $this->applyRules($room, $game, []);
            }

            $changed = true;
        }

        if ($room->getStatus() === RoomStatusEnum::LAUNCHING && $this->now() >= ($room->getRound()['launchAt'] ?? 0)) {
            $this->launch($room);
            $changed = true;
        }

        if ($changed) {
            $this->persist($room);
        }
    }

    public function snapshot(Room $room): array
    {
        $round = $room->getRound();

        return [
            'code' => $room->getCode(),
            'status' => $room->getStatus()->value,
            'host' => (string) $room->getHost()?->getId(),
            'maxPlayers' => $room->getMaxPlayers(),
            'played' => count($room->getHistory()),
            'members' => array_map(fn (RoomMember $member): array => [
                'id' => (string) $member->getUser()->getId(),
                'username' => $member->getUser()->getUsername(),
                'initials' => $member->getUser()->getInitials(),
                'avatar' => $member->getUser()->getAvatar(),
                'look' => $this->shop->cosmetics((int) $member->getUser()->getId()),
                'guest' => $member->getUser()->isGuest(),
                'spectator' => $member->isSpectator(),
                'seenAt' => $member->getSeenAt(),
            ], $this->members->findByRoom($room)),
            'round' => $round === [] ? null : $round + ['games' => $this->gamesOf($round['options'] ?? []), 'setup' => $this->setupOf($round)],
            'match' => $room->getStatus() === RoomStatusEnum::PLAYING ? $room->getMatchCode() : null,
            'presence' => RoomMember::PRESENCE_WINDOW,
            'serverTime' => $this->now(),
            'official' => $room->isOfficial(),
        ];
    }

    public function messages(Room $room): array
    {
        return array_map($this->message(...), $this->messages->findLatest($room, self::MESSAGES));
    }

    public function summary(Room $room): array
    {
        $members = $this->members->findByRoom($room);
        $now = $this->now();

        return [
            'room' => $room,
            'names' => array_map(fn (RoomMember $member): string => $member->getUser()->getUsername(), $members),
            'present' => count(array_filter($members, fn (RoomMember $member): bool => !$member->isSpectator() && $member->isPresent($now))),
            'game' => $room->getStatus() === RoomStatusEnum::PLAYING ? $this->matches->findByCode($room->getMatchCode())?->getGame() : null,
        ];
    }

    public function topic(Room $room): string
    {
        return 'rooms/' . $room->getCode();
    }

    protected function players(Room $room): array
    {
        $now = $this->now();

        return array_values(array_map(
            fn (RoomMember $member): User => $member->getUser(),
            array_filter($this->members->findByRoom($room), fn (RoomMember $member): bool => !$member->isSpectator() && $member->isPresent($now)),
        ));
    }

    protected function touch(Room $room, RoomMember $member): void
    {
        $returning = !$member->isPresent($this->now());

        if ($member->isSpectator() && count($this->players($room)) < $room->getMaxPlayers()) {
            $member->setSpectator(false);
            $returning = true;
        }

        $member->setSeenAt($this->now());
        $this->members->save($member, true);
        $this->advance($room, $returning);
    }

    protected function startRound(Room $room, int $count): void
    {
        $history = array_count_values($room->getHistory());
        $players = $this->players($room);
        $pool = array_values(array_filter(
            $this->games->findLive(),
            fn (Game $game): bool => $game->getMinPlayers() <= $count && $game->getMaxPlayers() >= $count && !$this->maintenance->blocksNewMatches($game)
                && array_filter($players, fn (User $player): bool => !$this->subscriptions->canAccess($player, $game)) === [],
        ));
        $number = ($room->getRound()['number'] ?? 0) + 1;

        if ($pool === []) {
            $room->setRound(['number' => $number - 1, 'blockedAt' => $this->now(), 'players' => $count]);

            return;
        }

        $options = [];

        while ($pool !== [] && count($options) < $room->getChoices()) {
            $weights = array_map(fn (Game $game): float => 1 / (1 + ($history[$game->getId()] ?? 0)), $pool);
            $pick = mt_rand() / mt_getrandmax() * array_sum($weights);

            foreach ($weights as $index => $weight) {
                $pick -= $weight;

                if ($pick <= 0 || $index === array_key_last($weights)) {
                    $options[] = $pool[$index]->getId();
                    array_splice($pool, $index, 1);
                    break;
                }
            }
        }

        $room->setStatus(RoomStatusEnum::VOTING)->setRound([
            'number' => $number,
            'options' => $options,
            'votes' => [],
            'players' => $count,
            'compatible' => count($options) + count($pool),
            'endsAt' => $this->now() + $room->getVoteDuration() * 1000,
        ]);
        $this->system($room, 'site.room.system.draw', ['games' => implode(', ', array_column($this->gamesOf($options), 'name'))]);
    }

    protected function voteIsOver(Room $room, array $players): bool
    {
        $votes = $room->getRound()['votes'] ?? [];
        $voted = array_filter($players, fn (User $user): bool => isset($votes[(string) $user->getId()]));

        return $this->now() >= ($room->getRound()['endsAt'] ?? 0) || ($players !== [] && count($voted) === count($players));
    }

    protected function resolve(Room $room): void
    {
        $round = $room->getRound();
        $counts = array_fill_keys($round['options'], 0);

        foreach ($round['votes'] as $game) {
            $counts[$game] = ($counts[$game] ?? 0) + 1;
        }

        $best = max($counts);
        $leaders = array_keys(array_filter($counts, fn (int $count): bool => $count === $best));

        $winner = $leaders[array_rand($leaders)];
        $game = $this->games->find($winner);

        if ($game !== null && ($game->getManifest()['settings'] ?? []) !== []) {
            $room->setStatus(RoomStatusEnum::CONFIGURING)->setRound($round + [
                'winner' => $winner,
                'tie' => count($leaders) > 1,
                'settings' => $this->gameManager->sdk($game)->configure([]),
                'configureEndsAt' => $this->now() + self::CONFIGURE_DELAY,
            ]);

            return;
        }

        $room->setStatus(RoomStatusEnum::LAUNCHING)->setRound($round + [
            'winner' => $winner,
            'tie' => count($leaders) > 1,
            'settings' => [],
            'launchAt' => $this->now() + self::LAUNCH_DELAY,
        ]);
    }

    protected function applyRules(Room $room, Game $game, array $values): void
    {
        $round = $room->getRound();
        $round['settings'] = $this->gameManager->sdk($game)->configure($values);
        $round['launchAt'] = $this->now() + self::LAUNCH_DELAY;
        unset($round['configureEndsAt']);
        $room->setStatus(RoomStatusEnum::LAUNCHING)->setRound($round);
    }

    protected function setupOf(array $round): array
    {
        $game = isset($round['winner']) ? $this->games->find((int) $round['winner']) : null;

        return array_map(fn (array $setting): array => [
            'name' => $setting['name'],
            'label' => $setting['label'] ?? $setting['name'],
            'suffix' => $setting['suffix'] ?? '',
            'choices' => $setting['choices'] ?? [],
            'default' => $setting['default'] ?? null,
        ], $game === null ? [] : ($game->getManifest()['settings'] ?? []));
    }

    protected function launch(Room $room): void
    {
        $game = $this->games->find((int) ($room->getRound()['winner'] ?? 0));
        $players = $this->players($room);

        if ($game === null || count($players) < max(self::MIN_PLAYERS, $game->getMinPlayers()) || count($players) > $game->getMaxPlayers() || $this->maintenance->blocksNewMatches($game)) {
            $room->setStatus(RoomStatusEnum::WAITING)->resetRound();

            return;
        }

        $match = $this->matches->room($room, $game, $players, $room->getRound()['settings'] ?? []);
        $room->setStatus(RoomStatusEnum::PLAYING)->setMatchCode($match->getCode())->addHistory($game->getId());
        $this->system($room, 'site.room.system.launched', ['game' => $game->getName()]);
    }

    protected function finishMatch(Room $room): bool
    {
        $match = $this->matches->findByCode($room->getMatchCode());

        if ($match !== null && !in_array($match->getStatus(), [GameMatchStatusEnum::FINISHED, GameMatchStatusEnum::CANCELLED], true)) {
            return false;
        }

        if ($match !== null && $match->getStatus() === GameMatchStatusEnum::FINISHED && $this->now() <= ($match->getTimeline()['voteEndsAt'] ?? 0)) {
            return false;
        }

        if ($match !== null && $match->getStatus() === GameMatchStatusEnum::FINISHED) {
            $snapshot = $this->matches->snapshot($match);
            $winner = $snapshot['scoreboard'][0]['player'] ?? null;
            $name = array_column($match->getPlayers(), 'username', 'id')[$winner] ?? '';
            $this->system($room, 'site.room.system.finished', ['game' => $match->getGame()->getName(), 'name' => $name]);
        }

        $room->setStatus(RoomStatusEnum::WAITING)->setMatchCode(null)->resetRound();

        return true;
    }

    protected function gamesOf(array $ids): array
    {
        return array_values(array_filter(array_map(function (int $id): ?array {
            $game = $this->games->find($id);

            return $game === null ? null : [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'slug' => $game->getSlug(),
                'color' => $game->getColor(),
                'icon' => $game->getIcon(),
                'players' => Translator::trans('site.room.players_range', ['min' => $game->getMinPlayers(), 'max' => $game->getMaxPlayers()], 'messages'),
            ];
        }, $ids)));
    }

    protected function member(Room $room, User $user, bool $spectator): RoomMember
    {
        return (new RoomMember())->setRoom($room)->setUser($user)->setSpectator($spectator)->setSeenAt($this->now());
    }

    protected function system(Room $room, string $key, array $parameters): void
    {
        $this->post($room, null, Translator::trans($key, $parameters, 'messages'));
    }

    protected function post(Room $room, ?User $user, string $text): void
    {
        $message = (new RoomMessage())->setRoom($room)->setUser($user)->setText($text);
        $this->messages->save($message, true);
        $this->mercure->publish(Update::json($this->topic($room), ['type' => 'message', 'message' => $this->message($message)]));
    }

    protected function message(RoomMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'user' => $message->getUser() === null ? null : (string) $message->getUser()->getId(),
            'username' => $message->getUser()?->getUsername(),
            'text' => $message->getText(),
        ];
    }

    protected function persist(Room $room): void
    {
        $this->rooms->save($room, true);
        $this->publish($room);
    }

    protected function publish(Room $room): void
    {
        $this->mercure->publish(Update::json($this->topic($room), ['type' => 'state', 'room' => $this->snapshot($room)]));
    }

    protected function code(): string
    {
        do {
            $code = '';

            for ($index = 0; $index < 6; $index++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $code = substr($code, 0, 3) . '-' . substr($code, 3);
        } while ($this->rooms->findByCode($code) !== null);

        return $code;
    }
}
