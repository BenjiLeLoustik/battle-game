<?php

namespace App\Shared\Social;

use App\Shared\Shop\ShopManager;
use App\Entity\ChatChannel;
use App\Entity\ChatMessage;
use App\Entity\ChatRead;
use App\Entity\Room;
use App\Entity\User;
use App\Repository\ChatChannelRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatReadRepository;
use App\Repository\UserRepository;
use App\Shared\Moderation\ModerationManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Social\Enum\ChatAccessEnum;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\Exception\SocialActionException;
use DateTime;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;

class ChatManager
{
    public const HISTORY = 60;
    private const COOLDOWN = 1;
    private const PREVIEW = 80;

    public function __construct(
        protected ChatMessageRepository $messages,
        protected ChatReadRepository $reads,
        protected UserRepository $users,
        protected FriendManager $friends,
        protected NotificationManager $notifications,
        protected MercureManagerInterface $mercure,
        protected ModerationManager $moderation,
        protected SettingManager $settings,
        protected ChatChannelRepository $channels,
        protected ShopManager $shop,
    ) {
    }

    public function channel(string $key): ?ChatChannel
    {
        $channel = str_starts_with($key, 'dm-') ? null : $this->channels->findBySlug($key);

        return $channel?->isActive() ? $channel : null;
    }

    public function visible(?User $user): array
    {
        return array_values(array_filter($this->channels->findActive(), fn (ChatChannel $channel): bool => $this->canRead($user, $channel)));
    }

    public function canRead(?User $user, ChatChannel $channel): bool
    {
        return $this->allowed($user, $channel->getReadAccess());
    }

    protected function allowed(?User $user, ChatAccessEnum $access): bool
    {
        return match ($access) {
            ChatAccessEnum::EVERYONE => true,
            ChatAccessEnum::MEMBERS => $user !== null && !$user->isGuest(),
            ChatAccessEnum::STAFF => $user !== null && $user->isAdmin(),
            ChatAccessEnum::NOBODY => false,
        };
    }

    public static function direct(User $user, User $other): string
    {
        $ids = [$user->getId(), $other->getId()];
        sort($ids);

        return 'dm-' . implode('-', $ids);
    }

    public function partner(string $key, User $user): ?User
    {
        if (!preg_match('/^dm-(\d+)-(\d+)$/', $key, $match)) {
            return null;
        }

        $other = (int) $match[1] === $user->getId() ? (int) $match[2] : ((int) $match[2] === $user->getId() ? (int) $match[1] : 0);

        return $other === 0 ? null : $this->users->find($other);
    }

    public function canWrite(?User $user, string $key): bool
    {
        if ($user === null || $user->isGuest() || $this->moderation->isMuted($user)) {
            return false;
        }

        $channel = $this->channel($key);

        if ($channel !== null) {
            return $this->canRead($user, $channel) && $this->allowed($user, $channel->getWriteAccess());
        }

        $other = $this->partner($key, $user);

        return $other !== null && $other->getDeletionRequestedAt() === null && $this->friends->allows($other, $user, 'message');
    }

    public function post(User $user, string $key, string $text, ?Room $room = null): void
    {
        $text = $this->settings->filter(mb_substr(trim($text), 0, ChatMessage::MAX_LENGTH));

        if (($text === '' && $room === null) || !$this->canWrite($user, $key) || $this->isFlooding($user, $this->channel($key))) {
            throw new SocialActionException();
        }

        $message = (new ChatMessage())->setChannel($key)->setUser($user)->setText($text)->setRoom($room);
        $this->messages->save($message, true);
        $view = $this->view($message);
        $other = $this->partner($key, $user);

        if ($other === null) {
            $this->mercure->publish(Update::json($this->topic($key), ['type' => 'message', 'message' => $view], !$this->channel($key)?->isPublic()));

            return;
        }

        $this->markRead($user, $key);

        foreach ([$user, $other] as $member) {
            $this->mercure->publish(Update::json($this->notifications->topic($member), ['type' => 'dm', 'key' => $key, 'message' => $view], true));
        }

        $this->notifications->notify($other, NotificationTypeEnum::MESSAGE, $this->friends->sender($user) + [
            'preview' => mb_strimwidth($text !== '' ? $text : (string) $room?->getName(), 0, self::PREVIEW, '…'),
        ]);
    }

    public function typing(User $user, string $key): void
    {
        if (!$this->canWrite($user, $key)) {
            return;
        }

        $payload = ['type' => 'typing', 'key' => $key, 'user' => (string) $user->getId(), 'username' => $user->getUsername()];
        $other = $this->partner($key, $user);

        $this->mercure->publish($other === null
            ? Update::json($this->topic($key), $payload, !$this->channel($key)?->isPublic())
            : Update::json($this->notifications->topic($other), $payload, true));
    }

    public function history(string $key, ?User $viewer): array
    {
        $messages = array_reverse($this->messages->findBy(['channel' => $key], ['id' => 'DESC'], self::HISTORY));
        $hidden = $viewer === null ? [] : array_map(fn ($block): int => $block->getBlocked()->getId(), $this->friends->blocked($viewer));

        return array_values(array_map($this->view(...), array_filter(
            $messages,
            fn (ChatMessage $message): bool => !in_array($message->getUser()?->getId(), $hidden, true),
        )));
    }

    public function markRead(User $user, string $key): void
    {
        $last = (int) $this->messages->getOrm()->getConnection()->fetchOne('SELECT COALESCE(MAX(id), 0) FROM chat_message WHERE channel = :channel', ['channel' => $key]);
        $read = $this->reads->findOneBy(['user' => $user, 'channel' => $key]) ?? (new ChatRead())->setUser($user)->setChannel($key);
        $this->reads->save($read->setLastId($last), true);
    }

    public function unread(User $user): array
    {
        return array_map('intval', $this->messages->getOrm()->getConnection()->fetchAllKeyValue(
            'SELECT m.channel, COUNT(*) FROM chat_message m LEFT JOIN chat_read r ON r.channel = m.channel AND r.user_id = :user'
            . ' WHERE (m.user_id IS NULL OR m.user_id <> :user) AND m.id > COALESCE(r.last_id, 0)'
            . ' AND (m.channel NOT LIKE :dm OR m.channel REGEXP :mine) GROUP BY m.channel',
            ['user' => $user->getId(), 'dm' => 'dm-%', 'mine' => sprintf('^dm-(%1$d-[0-9]+|[0-9]+-%1$d)$', $user->getId())],
        ));
    }

    public function conversations(User $user): array
    {
        $rows = $this->messages->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT channel, MAX(id) AS last FROM chat_message WHERE channel REGEXP :mine GROUP BY channel ORDER BY last DESC LIMIT 30',
            ['mine' => sprintf('^dm-(%1$d-[0-9]+|[0-9]+-%1$d)$', $user->getId())],
        );
        $conversations = [];

        foreach ($rows as $row) {
            $other = $this->partner($row['channel'], $user);
            $last = $this->messages->find((int) $row['last']);

            if ($other !== null && $last !== null && !$this->friends->hasBlocked($user, $other)) {
                $conversations[] = ['key' => $row['channel'], 'user' => $other, 'last' => $last];
            }
        }

        return $conversations;
    }

    public function topic(string $key): string
    {
        return 'chat/' . $key;
    }

    public function view(ChatMessage $message): array
    {
        $user = $message->getUser();
        $room = $message->getRoom();

        return [
            'id' => $message->getId(),
            'user' => $user === null ? null : (string) $user->getId(),
            'username' => $user?->getUsername(),
            'initials' => $user?->getInitials(),
            'avatar' => $user?->getAvatar(),
            'look' => $user === null ? [] : $this->shop->cosmetics((int) $user->getId()),
            'staff' => $user !== null && in_array('ROLE_ADMIN', $user->getRoles(), true),
            'text' => $message->getText(),
            'date' => ($message->getCreatedAt() ?? new DateTime())->format(DATE_ATOM),
            'room' => $room === null ? null : ['code' => $room->getCode(), 'name' => $room->getName(), 'max' => $room->getMaxPlayers()],
        ];
    }

    public function purge(ChatChannel $channel): void
    {
        $connection = $this->messages->getOrm()->getConnection();
        $connection->executeStatement('DELETE FROM chat_message WHERE channel = :channel', ['channel' => $channel->getSlug()]);
        $connection->executeStatement('DELETE FROM chat_read WHERE channel = :channel', ['channel' => $channel->getSlug()]);
    }

    public function count(ChatChannel $channel): int
    {
        return (int) $this->messages->getOrm()->getConnection()->fetchOne('SELECT COUNT(*) FROM chat_message WHERE channel = :channel', ['channel' => $channel->getSlug()]);
    }

    protected function isFlooding(User $user, ?ChatChannel $channel): bool
    {
        $last = $this->messages->findOneBy(['user' => $user], ['id' => 'DESC']);

        if ($last !== null && $last->getCreatedAt() > new DateTime('-' . self::COOLDOWN . ' seconds')) {
            return true;
        }

        if ($channel === null || $channel->getCooldown() <= self::COOLDOWN || $user->isAdmin()) {
            return false;
        }

        $previous = $this->messages->findOneBy(['user' => $user, 'channel' => $channel->getSlug()], ['id' => 'DESC']);

        return $previous !== null && $previous->getCreatedAt() > new DateTime('-' . $channel->getCooldown() . ' seconds');
    }
}
