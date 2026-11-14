<?php

namespace App\Shared\Social;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Shared\Mail\MailManager;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Translation\Translator;
use DateTime;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;

class NotificationManager
{
    public const LIST_SIZE = 15;
    private const PREFERENCES = [
        'friend_request' => 'friend_request',
        'friend_accepted' => 'friend_request',
        'room_invite' => 'room_invite',
        'message' => 'message',
        'email_changed' => 'security',
        'password_changed' => 'security',
        'game_released' => 'events',
        'season_started' => 'events',
        'maintenance_planned' => 'events',
        'studio_review' => 'studio',
        'studio_approved' => 'studio',
        'studio_rejected' => 'studio',
        'studio_lead' => 'studio',
        'shop_paid' => 'shop',
        'shop_shipped' => 'shop',
        'shop_refunded' => 'shop',
        'season_reward' => 'events',
        'referral_reward' => 'events',
        'tournament_reward' => 'events',
        'tournament_qualified' => 'events',
    ];

    public function __construct(
        protected NotificationRepository $notifications,
        protected UserRepository $users,
        protected MercureManagerInterface $mercure,
        protected MailManager $mail,
        protected RoutingManagerInterface $router,
    ) {
    }

    public function notify(User $user, NotificationTypeEnum $type, array $data): void
    {
        $preferences = $user->getPreferences();
        $event = self::PREFERENCES[$type->value];

        if ($user->isGuest() || $user->getDeletionRequestedAt() !== null) {
            return;
        }

        if ($preferences->wants($event, 'site')) {
            $notification = $this->merge($user, $type, $data) ?? (new Notification())->setUser($user)->setType($type);
            $notification->setData($data)->setReadAt(null);
            $this->notifications->save($notification, true);
            $this->publish($user, ['type' => 'notification', 'notification' => $this->view($notification)]);
        }

        if ($preferences->wants($event, 'email') && !$this->isQuiet($user)) {
            $locale = $this->mail->locale($user);
            $view = $this->view((new Notification())->setUser($user)->setType($type)->setData($data), true, $locale);
            $this->mail->compose(
                (string) $user->getEmail(),
                $view['text'],
                $view['text'],
                $view['preview'] ? '« ' . $view['preview'] . ' »' : '',
                ['label' => Translator::trans('site.notifications.open', [], 'messages', $locale), 'url' => $view['url']],
                Translator::trans('site.notifications.email_footer', [], 'messages', $locale),
                $event === 'events' ? $this->mail->unsubscribeUrl($user) : null,
                $locale,
            );
        }
    }

    public function broadcast(NotificationTypeEnum $type, array $data): int
    {
        $users = $this->users->findBy(['guest' => false]);

        foreach ($users as $user) {
            $this->notify($user, $type, $data);
        }

        return count($users);
    }

    public function latest(User $user): array
    {
        return array_map(fn (Notification $notification): array => $this->view($notification), $this->notifications->findBy(['user' => $user], ['updatedAt' => 'DESC'], self::LIST_SIZE));
    }

    public function unread(User $user): int
    {
        return $this->notifications->countUnread($user);
    }

    public function markRead(User $user): void
    {
        $this->notifications->getOrm()->getConnection()->executeStatement(
            'UPDATE notification SET read_at = NOW() WHERE user_id = :user AND read_at IS NULL',
            ['user' => $user->getId()],
        );
        $this->publish($user, ['type' => 'read']);
    }

    public function publish(User $user, array $payload): void
    {
        $this->mercure->publish(Update::json($this->topic($user), $payload + ['unread' => $this->unread($user)], true));
    }

    public function topic(User $user): string
    {
        return 'users/' . $user->getId();
    }

    public function view(Notification $notification, bool $absolute = false, ?string $locale = null): array
    {
        $data = $notification->getData();

        return [
            'id' => $notification->getId(),
            'type' => $notification->getType()->value,
            'text' => Translator::trans('site.notifications.types.' . $notification->getType()->value, array_filter($data, 'is_scalar') + [
                'name' => '',
                'room' => $data['roomName'] ?? '',
                'count' => 1,
            ], 'messages', $locale),
            'preview' => $data['preview'] ?? null,
            'url' => $this->url($notification->getType(), $data, $absolute),
            'initials' => $data['initials'] ?? 'BG',
            'avatar' => $data['avatar'] ?? null,
            'date' => ($notification->getUpdatedAt() ?? $notification->getCreatedAt() ?? new DateTime())->format(DATE_ATOM),
            'read' => $notification->getReadAt() !== null,
        ];
    }

    protected function merge(User $user, NotificationTypeEnum $type, array &$data): ?Notification
    {
        if ($type !== NotificationTypeEnum::MESSAGE) {
            return null;
        }

        foreach ($this->notifications->findBy(['user' => $user, 'type' => $type]) as $notification) {
            if ($notification->getReadAt() === null && ($notification->getData()['from'] ?? null) === ($data['from'] ?? null)) {
                $data['count'] = ($notification->getData()['count'] ?? 1) + 1;
                $notification->setUpdated();

                return $notification;
            }
        }

        return null;
    }

    protected function url(NotificationTypeEnum $type, array $data, bool $absolute): string
    {
        [$route, $parameters] = match ($type) {
            NotificationTypeEnum::FRIEND_REQUEST => ['site_friend_index', []],
            NotificationTypeEnum::FRIEND_ACCEPTED => ['site_profile_show', ['username' => $data['name'] ?? '']],
            NotificationTypeEnum::ROOM_INVITE => ['site_room_show', ['code' => $data['room'] ?? '']],
            NotificationTypeEnum::MESSAGE => ['site_chat_dm', ['username' => $data['name'] ?? '']],
            NotificationTypeEnum::EMAIL_CHANGED, NotificationTypeEnum::PASSWORD_CHANGED => ['site_settings_account', []],
            NotificationTypeEnum::GAME_RELEASED => ['site_game_show', ['slug' => $data['slug'] ?? '']],
            NotificationTypeEnum::SEASON_STARTED, NotificationTypeEnum::SEASON_REWARD => ['site_leaderboard_index', []],
            NotificationTypeEnum::REFERRAL_REWARD => ['site_settings_referral', []],
            NotificationTypeEnum::TOURNAMENT_REWARD, NotificationTypeEnum::TOURNAMENT_QUALIFIED => ['site_tournament_show', ['id' => $data['id'] ?? 0]],
            NotificationTypeEnum::MAINTENANCE_PLANNED => ['site_maintenance_show', []],
            NotificationTypeEnum::STUDIO_REVIEW, NotificationTypeEnum::STUDIO_APPROVED, NotificationTypeEnum::STUDIO_REJECTED, NotificationTypeEnum::STUDIO_LEAD => ['admin_studio_show', ['slug' => $data['slug'] ?? '']],
            NotificationTypeEnum::SHOP_PAID, NotificationTypeEnum::SHOP_SHIPPED, NotificationTypeEnum::SHOP_REFUNDED => ['site_shop_orders', []],
        };

        return $this->router->generate($route, $parameters, $absolute);
    }

    protected function isQuiet(User $user): bool
    {
        $dnd = $user->getPreferences()->section('notifications')['dnd'];

        if (!$dnd['enabled']) {
            return false;
        }

        $now = (new DateTime())->format('H:i');

        return $dnd['from'] <= $dnd['to'] ? $now >= $dnd['from'] && $now < $dnd['to'] : $now >= $dnd['from'] || $now < $dnd['to'];
    }
}
