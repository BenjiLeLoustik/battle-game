<?php

namespace App\Admin\User;

use App\Entity\User;
use App\Shared\Moderation\Enum\SanctionTypeEnum;
use App\Shared\Moderation\ModerationManager;
use App\Shared\Social\Enum\ReportReasonEnum;

class UserAdminRequest
{
    public const STATUSES = ['active', 'suspension', 'ban'];

    public string $username = '';
    public string $firstName = '';
    public string $email = '';
    public ?string $country = null;
    public string $note = '';
    public string $status = 'active';
    public int $duration = 168;
    public ReportReasonEnum $reason = ReportReasonEnum::OTHER;
    public string $message = '';

    public static function fromArray(array $data, array $countries = []): static
    {
        $request = new static();
        $request->username = trim((string) ($data['username'] ?? ''));
        $request->firstName = mb_substr(trim((string) ($data['first_name'] ?? '')), 0, 50);
        $request->email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $request->country = in_array($data['country'] ?? '', $countries, true) ? $data['country'] : null;
        $request->note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 1000);
        $request->status = in_array($data['status'] ?? '', self::STATUSES, true) ? $data['status'] : 'active';
        $request->duration = in_array((int) ($data['duration'] ?? 0), ModerationManager::DURATIONS, true) ? (int) $data['duration'] : 168;
        $request->reason = ReportReasonEnum::tryFrom((string) ($data['reason'] ?? '')) ?? ReportReasonEnum::OTHER;
        $request->message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 500);

        return $request;
    }

    public static function fromUser(User $user, string $status): static
    {
        $request = new static();
        $request->username = (string) $user->getUsername();
        $request->firstName = (string) $user->getFirstName();
        $request->email = (string) $user->getEmail();
        $request->country = $user->getCountry();
        $request->note = (string) $user->getAdminNote();
        $request->status = $status;

        return $request;
    }

    public function errors(?User $user, callable $exists): array
    {
        return array_filter([
            'username' => !preg_match(User::USERNAME_PATTERN, $this->username)
                ? 'user.username.invalid'
                : ($this->username !== $user?->getUsername() && $exists('username', $this->username) ? 'user.username.taken' : null),
            'email' => !filter_var($this->email, FILTER_VALIDATE_EMAIL)
                ? 'user.email.invalid'
                : ($this->email !== $user?->getEmail() && $exists('email', $this->email) ? 'user.email.taken' : null),
        ]);
    }

    public function applyTo(User $user): User
    {
        return $user
            ->setUsername($this->username)
            ->setFirstName($this->firstName !== '' ? $this->firstName : $this->username)
            ->setEmail($this->email)
            ->setCountry($this->country)
            ->setAdminNote($this->note);
    }

    public function sanctionType(): ?SanctionTypeEnum
    {
        return SanctionTypeEnum::tryFrom($this->status);
    }
}
