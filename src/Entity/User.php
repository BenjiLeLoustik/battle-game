<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserRepository;
use App\Shared\User\UserPreferences;
use App\Shared\Validator\Constraint\Unique;
use DateTime;
use NeoPHP\Component\Validator\Constraint as Assert;
use NeoPHP\Package\Orm\Mapping as ORM;
use NeoPHP\Package\Security\Contract\PasswordAuthenticatedUserInterface;
use NeoPHP\Package\Security\Contract\UserInterface;

#[ORM\Entity(repository: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdTrait;
    use TimestampTrait;

    public const USERNAME_PATTERN = '/^[A-Za-z0-9_]{3,30}$/';
    public const GUEST_DOMAIN = 'guest.battlegame.local';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'user.first_name.not_blank')]
    #[Assert\Length(max: 50, maxMessage: 'user.first_name.too_long')]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'user.last_name.not_blank')]
    #[Assert\Length(max: 50, maxMessage: 'user.last_name.too_long')]
    private ?string $lastName = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank(message: 'user.username.not_blank')]
    #[Assert\Regex(pattern: self::USERNAME_PATTERN, message: 'user.username.invalid')]
    #[Unique(message: 'user.username.taken')]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'user.email.not_blank')]
    #[Assert\Email(message: 'user.email.invalid')]
    #[Unique(message: 'user.email.taken')]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 32, nullable: true, unique: true)]
    private ?string $discordId = null;

    #[ORM\Column(nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private bool $guest = false;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\ManyToOne(Game::class, onDelete: 'SET NULL')]
    private ?Game $favoriteGame = null;

    #[ORM\Column(nullable: true)]
    private ?string $discordAvatar = null;

    #[ORM\Column(type: 'json')]
    private array $preferences = [];

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $usernameChangedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $passwordChangedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $deletionRequestedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $referredBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $referralRewardedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $showcase = null;

    public function getReferredBy(): ?User
    {
        return $this->referredBy;
    }

    public function setReferredBy(?User $referredBy): static
    {
        $this->referredBy = $referredBy;

        return $this;
    }

    public function getReferralRewardedAt(): ?DateTime
    {
        return $this->referralRewardedAt;
    }

    public function setReferralRewardedAt(?DateTime $referralRewardedAt): static
    {
        $this->referralRewardedAt = $referralRewardedAt;

        return $this;
    }

    public function getShowcase(): array
    {
        return $this->showcase ?? [];
    }

    public function setShowcase(?array $showcase): static
    {
        $this->showcase = $showcase;

        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $totpEnabledAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $totpRecovery = null;

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function getTotpEnabledAt(): ?DateTime
    {
        return $this->totpEnabledAt;
    }

    public function setTotpEnabledAt(?DateTime $totpEnabledAt): static
    {
        $this->totpEnabledAt = $totpEnabledAt;

        return $this;
    }

    public function getTotpRecovery(): ?array
    {
        return $this->totpRecovery;
    }

    public function setTotpRecovery(?array $totpRecovery): static
    {
        $this->totpRecovery = $totpRecovery;

        return $this;
    }

    public function hasTotp(): bool
    {
        return $this->totpSecret !== null && $this->totpEnabledAt !== null;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $seenAt = null;

    #[ORM\ManyToOne(Badge::class, onDelete: 'SET NULL')]
    private ?Badge $featuredBadge = null;

    #[ORM\ManyToOne(AdminRole::class, onDelete: 'SET NULL')]
    private ?AdminRole $adminRole = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $resetExpiresAt = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = trim($username);

        return $this;
    }

    public function getHandle(): string
    {
        return '@' . $this->getUsername();
    }

    public function getInitials(): string
    {
        return self::initialsOf((string) $this->firstName, (string) $this->lastName);
    }

    public static function initialsOf(string $firstName, string $lastName): string
    {
        $initials = mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);

        return mb_strtoupper(mb_strlen($initials) > 1 ? $initials : mb_substr($firstName, 0, 2));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, $this->guest ? 'ROLE_GUEST' : 'ROLE_USER']));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->passwordChangedAt = new DateTime();

        return $this;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function isGuest(): bool
    {
        return $this->guest;
    }

    public function setGuest(bool $guest): static
    {
        $this->guest = $guest;

        return $this;
    }

    public function clearPassword(): static
    {
        $this->password = null;

        return $this;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function getPasswordChangedAt(): ?DateTime
    {
        return $this->passwordChangedAt;
    }

    public function setUsernameChangedAt(?DateTime $usernameChangedAt): static
    {
        $this->usernameChangedAt = $usernameChangedAt;

        return $this;
    }

    public function getUsernameChangedAt(): ?DateTime
    {
        return $this->usernameChangedAt;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio === null || trim($bio) === '' ? null : trim($bio);

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getFavoriteGame(): ?Game
    {
        return $this->favoriteGame;
    }

    public function setFavoriteGame(?Game $favoriteGame): static
    {
        $this->favoriteGame = $favoriteGame;

        return $this;
    }

    public function getDiscordAvatar(): ?string
    {
        return $this->discordAvatar;
    }

    public function setDiscordAvatar(?string $discordAvatar): static
    {
        $this->discordAvatar = $discordAvatar;

        return $this;
    }

    public function getPreferences(): UserPreferences
    {
        return new UserPreferences($this->preferences);
    }

    public function setPreferences(UserPreferences $preferences): static
    {
        $this->preferences = $preferences->all();

        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function requestEmail(?string $email, ?string $token): static
    {
        $this->pendingEmail = $email;
        $this->emailToken = $token;

        return $this;
    }

    public function getEmailToken(): ?string
    {
        return $this->emailToken;
    }

    public function getDeletionRequestedAt(): ?DateTime
    {
        return $this->deletionRequestedAt;
    }

    public function setDeletionRequestedAt(?DateTime $deletionRequestedAt): static
    {
        $this->deletionRequestedAt = $deletionRequestedAt;

        return $this;
    }

    public function getSeenAt(): ?DateTime
    {
        return $this->seenAt;
    }

    public function setSeenAt(?DateTime $seenAt): static
    {
        $this->seenAt = $seenAt;

        return $this;
    }

    public function getFeaturedBadge(): ?Badge
    {
        return $this->featuredBadge;
    }

    public function setFeaturedBadge(?Badge $featuredBadge): static
    {
        $this->featuredBadge = $featuredBadge;

        return $this;
    }

    public function getAdminRole(): ?AdminRole
    {
        return $this->adminRole;
    }

    public function setAdminRole(?AdminRole $adminRole): static
    {
        $this->adminRole = $adminRole;

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote === null || trim($adminNote) === '' ? null : trim($adminNote);

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function getResetExpiresAt(): ?DateTime
    {
        return $this->resetExpiresAt;
    }

    public function setReset(?string $token, ?DateTime $expiresAt): static
    {
        $this->resetToken = $token;
        $this->resetExpiresAt = $expiresAt;

        return $this;
    }
}
