<?php

namespace App\Site\Account;

use App\Entity\User;
use App\Repository\BadgeRepository;
use App\Repository\CountryRepository;
use App\Repository\GameRepository;
use App\Repository\GameResultRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Shared\Badge\BadgeManager;
use App\Shared\Mail\MailManager;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use App\Shared\Translation\Translator;
use DateTime;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Database\Contract\ConnectionInterface;
use NeoPHP\Component\Http\Request\UploadedFile;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Image\ImageManagerInterface;
use NeoPHP\Package\Security\Hasher\UserPasswordHasher;

class AccountManager
{
    public const USERNAME_DELAY = 30;
    public const DELETION_DELAY = 14;
    private const AVATAR_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
    private const AVATAR_MAX_SIZE = 2097152;
    private const AVATAR_SIZE = 256;
    private const AVATAR_DIRECTORY = '/uploads/avatars';

    public function __construct(
        protected UserRepository $users,
        protected NotificationManager $notifications,
        protected GameRepository $games,
        protected GameResultRepository $results,
        protected RoomRepository $rooms,
        protected BadgeRepository $badges,
        protected CountryRepository $countries,
        protected BadgeManager $badgeManager,
        protected UserPasswordHasher $hasher,
        protected MailManager $mail,
        protected RoutingManagerInterface $router,
        protected ImageManagerInterface $images,
        #[Autowire(param: 'kernel.public_path')]
        protected string $publicPath,
    ) {
    }

    public function updateProfile(User $user, array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $country = $this->countries->findByCode((string) ($data['country'] ?? ''))?->getCode();
        $bio = mb_substr(trim((string) ($data['bio'] ?? '')), 0, 160);
        $game = $this->games->find((int) ($data['favorite_game'] ?? 0));
        $badge = $this->badges->find((int) ($data['featured_badge'] ?? 0));
        $errors = $username === $user->getUsername() ? [] : $this->usernameErrors($user, $username);

        if ($errors !== []) {
            return $errors;
        }

        if ($username !== $user->getUsername()) {
            $user->setUsername($username)->setUsernameChangedAt(new DateTime());
        }

        $user->setCountry($country)
            ->setBio($bio)
            ->setFavoriteGame($game?->isPlayableBy(false) ? $game : null)
            ->setFeaturedBadge($badge !== null && $this->badgeManager->owns($user, $badge) ? $badge : null);
        $this->users->save($user, true);

        return [];
    }

    public function canRename(User $user): ?DateTime
    {
        $next = $user->getUsernameChangedAt() === null ? null : (clone $user->getUsernameChangedAt())->modify('+' . self::USERNAME_DELAY . ' days');

        return $next !== null && $next > new DateTime() ? $next : null;
    }

    public function uploadAvatar(User $user, ?UploadedFile $file): ?string
    {
        if ($file === null || !$file->isValid() || !in_array($file->getMimeType(), self::AVATAR_TYPES, true)) {
            return 'account.avatar.type';
        }

        if ($file->getSize() > self::AVATAR_MAX_SIZE) {
            return 'account.avatar.size';
        }

        $name = sprintf('%d-%s.webp', $user->getId(), bin2hex(random_bytes(6)));
        $directory = $this->publicPath . self::AVATAR_DIRECTORY;

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->images->open($file->getPath())->autoOrient()->cover(self::AVATAR_SIZE, self::AVATAR_SIZE, 'center', true)->save($directory . '/' . $name, 'webp');
        $this->removeAvatar($user);
        $user->setAvatar(self::AVATAR_DIRECTORY . '/' . $name);
        $this->users->save($user, true);

        return null;
    }

    public function useDiscordAvatar(User $user): void
    {
        if ($user->getDiscordAvatar() !== null) {
            $this->removeAvatar($user);
            $user->setAvatar($user->getDiscordAvatar());
            $this->users->save($user, true);
        }
    }

    public function removeAvatar(User $user): void
    {
        $avatar = (string) $user->getAvatar();

        if (str_starts_with($avatar, self::AVATAR_DIRECTORY . '/') && is_file($this->publicPath . $avatar)) {
            unlink($this->publicPath . $avatar);
        }

        $user->setAvatar(null);
        $this->users->save($user, true);
    }

    public function requestEmail(User $user, string $email, string $password): ?string
    {
        $email = mb_strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@' . User::GUEST_DOMAIN)) {
            return 'user.email.invalid';
        }

        if ($email === $user->getEmail() || $this->users->findOneBy(['email' => $email]) !== null) {
            return 'user.email.taken';
        }

        if (!$this->checkPassword($user, $password)) {
            return 'account.password.wrong';
        }

        $token = bin2hex(random_bytes(32));
        $user->requestEmail($email, hash('sha256', $token));
        $this->users->save($user, true);
        $locale = $this->mail->locale($user);
        $this->mail->compose(
            $email,
            Translator::trans('site.settings.mail.email_subject', [], 'messages', $locale),
            Translator::trans('site.settings.mail.title', [], 'messages', $locale),
            Translator::trans('site.settings.mail.text', ['name' => $user->getUsername(), 'email' => $email], 'messages', $locale),
            ['label' => Translator::trans('site.settings.mail.action', [], 'messages', $locale), 'url' => $this->router->generate('site_settings_email_confirm', ['token' => $token], true)],
            Translator::trans('site.settings.mail.ignore', [], 'messages', $locale),
            null,
            $locale,
        );

        return null;
    }

    public function confirmEmail(User $user, string $token): bool
    {
        if ($user->getEmailToken() === null || !hash_equals($user->getEmailToken(), hash('sha256', $token)) || $this->users->findOneBy(['email' => $user->getPendingEmail()]) !== null) {
            return false;
        }

        $this->deleteDevices($user);
        $user->setEmail((string) $user->getPendingEmail())->requestEmail(null, null);
        $this->users->save($user, true);
        $this->notifications->notify($user, NotificationTypeEnum::EMAIL_CHANGED, ['email' => $user->getEmail()]);

        return true;
    }

    public function changePassword(User $user, string $current, string $password, string $confirmation): ?string
    {
        if ($user->hasPassword() && !$this->checkPassword($user, $current)) {
            return 'account.password.wrong';
        }

        if (mb_strlen($password) < 10 || mb_strlen($password) > 4096) {
            return 'user.password.too_short';
        }

        if ($password !== $confirmation) {
            return 'account.password.mismatch';
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->users->save($user, true);
        $this->notifications->notify($user, NotificationTypeEnum::PASSWORD_CHANGED, []);

        return null;
    }

    public function unlinkDiscord(User $user): ?string
    {
        if (!$user->hasPassword()) {
            return 'account.discord.password_required';
        }

        $user->setDiscordId(null)->setDiscordAvatar(null);
        $this->users->save($user, true);

        return null;
    }

    public function devices(User $user, ?string $current): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT series, user_agent, ip, last_used_at FROM remember_me_tokens WHERE identifier = :identifier ORDER BY last_used_at DESC',
            ['identifier' => $user->getUserIdentifier()],
        );

        return array_map(fn (array $row): array => [
            'series' => $row['series'],
            'device' => $this->device((string) $row['user_agent']),
            'ip' => $row['ip'],
            'lastUsed' => (new DateTime())->setTimestamp((int) $row['last_used_at']),
            'current' => $row['series'] === $current,
        ], $rows);
    }

    public function revokeDevice(User $user, string $series): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM remember_me_tokens WHERE identifier = :identifier AND series = :series',
            ['identifier' => $user->getUserIdentifier(), 'series' => $series],
        );
    }

    public function revokeOthers(User $user, ?string $current): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM remember_me_tokens WHERE identifier = :identifier AND series <> :series',
            ['identifier' => $user->getUserIdentifier(), 'series' => (string) $current],
        );
    }

    public function export(User $user): array
    {
        return [
            'profile' => [
                'username' => $user->getUsername(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'bio' => $user->getBio(),
                'country' => $user->getCountry(),
                'favoriteGame' => $user->getFavoriteGame()?->getName(),
                'discordLinked' => $user->getDiscordId() !== null,
                'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
            ],
            'preferences' => $user->getPreferences()->all(),
            'games' => array_map(fn ($result): array => [
                'game' => $result->getGame()->getName(),
                'position' => $result->getPosition(),
                'points' => $result->getPoints(),
                'stats' => $result->getStats(),
                'playedAt' => $result->getCreatedAt()?->format(DATE_ATOM),
            ], $this->results->findBy(['user' => $user], ['id' => 'ASC'])),
            'rooms' => array_map(fn ($room): array => ['name' => $room->getName(), 'code' => $room->getCode()], $this->rooms->findJoinedBy($user)),
        ];
    }

    public function requestDeletion(User $user, string $username, string $password): ?string
    {
        if ($username !== $user->getUsername()) {
            return 'account.delete.username';
        }

        if ($user->hasPassword() && !$this->checkPassword($user, $password)) {
            return 'account.password.wrong';
        }

        $user->setDeletionRequestedAt(new DateTime());
        $this->users->save($user, true);

        return null;
    }

    public function cancelDeletion(User $user): void
    {
        $user->setDeletionRequestedAt(null);
        $this->users->save($user, true);
    }

    public function deletionDate(User $user): ?DateTime
    {
        return $user->getDeletionRequestedAt() === null ? null : (clone $user->getDeletionRequestedAt())->modify('+' . self::DELETION_DELAY . ' days');
    }

    public function anonymize(User $user): void
    {
        $this->deleteDevices($user);
        $this->removeAvatar($user);
        $this->connection()->executeStatement('DELETE FROM room_member WHERE user_id = :user', ['user' => $user->getId()]);
        $preferences = $user->getPreferences()->with('privacy', ['public' => false, 'leaderboard' => false]);

        $user->setUsername('deleted_' . $user->getId())
            ->setFirstName(Translator::trans('site.settings.delete.deleted_name', [], 'messages'))
            ->setLastName('')
            ->setEmail(sprintf('deleted-%d@%s', $user->getId(), User::GUEST_DOMAIN))
            ->setRoles([])
            ->setDiscordId(null)
            ->setDiscordAvatar(null)
            ->setBio(null)
            ->setCountry(null)
            ->setFavoriteGame(null)
            ->setFeaturedBadge(null)
            ->setPreferences($preferences)
            ->clearPassword()
            ->requestEmail(null, null);
        $this->users->save($user, true);
    }

    protected function usernameErrors(User $user, string $username): array
    {
        if ($this->canRename($user) !== null) {
            return ['username' => 'account.username.too_soon'];
        }

        if (!preg_match(User::USERNAME_PATTERN, $username)) {
            return ['username' => 'user.username.invalid'];
        }

        return $this->users->findOneBy(['username' => $username]) !== null ? ['username' => 'user.username.taken'] : [];
    }

    protected function checkPassword(User $user, string $password): bool
    {
        return !$user->hasPassword() || $this->hasher->isPasswordValid($user, $password);
    }

    protected function deleteDevices(User $user): void
    {
        $this->revokeOthers($user, null);
    }

    protected function device(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => Translator::trans('site.settings.account.unknown_browser', [], 'messages'),
        };
        $system = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser . ($system !== '' ? ' · ' . $system : ''));
    }

    protected function connection(): ConnectionInterface
    {
        return $this->users->getOrm()->getConnection();
    }
}
