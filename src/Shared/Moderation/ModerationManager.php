<?php

namespace App\Shared\Moderation;

use App\Entity\Report;
use App\Entity\Sanction;
use App\Entity\User;
use App\Repository\ReportRepository;
use App\Repository\SanctionRepository;
use App\Shared\Moderation\Enum\SanctionTypeEnum;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\Enum\ReportStatusEnum;
use DateTime;

class ModerationManager
{
    public const DURATIONS = [24, 72, 168, 720];

    protected array $cache = [];

    public function __construct(
        protected SanctionRepository $sanctions,
        protected ReportRepository $reports,
    ) {
    }

    public function active(User $user): array
    {
        return $this->cache[$user->getId()] ??= array_values(array_filter(
            $this->sanctions->findBy(['user' => $user], ['id' => 'DESC']),
            fn (Sanction $sanction): bool => $sanction->isActive() && $sanction->getType() !== SanctionTypeEnum::WARNING,
        ));
    }

    public function lockout(User $user): ?Sanction
    {
        foreach ($this->active($user) as $sanction) {
            if (in_array($sanction->getType(), [SanctionTypeEnum::SUSPENSION, SanctionTypeEnum::BAN], true)) {
                return $sanction;
            }
        }

        return null;
    }

    public function isMuted(User $user): bool
    {
        return $this->active($user) !== [];
    }

    public function sanction(User $user, ?User $author, SanctionTypeEnum $type, ReportReasonEnum $reason, ?string $message, ?int $hours): Sanction
    {
        $sanction = (new Sanction())
            ->setUser($user)
            ->setAuthor($author)
            ->setType($type)
            ->setReason($reason)
            ->setMessage($message === null || trim($message) === '' ? null : mb_substr(trim($message), 0, 500))
            ->setEndsAt(in_array($type, [SanctionTypeEnum::MUTE, SanctionTypeEnum::SUSPENSION], true) && $hours !== null ? new DateTime('+' . $hours . ' hours') : null);

        $this->sanctions->save($sanction, true);
        $this->resolve($user, ReportStatusEnum::RESOLVED);
        unset($this->cache[$user->getId()]);

        return $sanction;
    }

    public function revoke(Sanction $sanction): void
    {
        $this->sanctions->save($sanction->setRevokedAt(new DateTime()), true);
        unset($this->cache[$sanction->getUser()->getId()]);
    }

    public function resolve(User $user, ReportStatusEnum $status): void
    {
        foreach ($this->reports->findBy(['reported' => $user, 'status' => ReportStatusEnum::OPEN]) as $report) {
            $this->reports->save($report->setStatus($status));
        }

        $this->reports->getOrm()->flush();
    }

    public function history(User $user): array
    {
        return $this->sanctions->findBy(['user' => $user], ['id' => 'DESC']);
    }

    public function activeSanctions(): array
    {
        return array_values(array_filter(
            $this->sanctions->findBy([], ['id' => 'DESC'], 200),
            fn (Sanction $sanction): bool => $sanction->isActive() && $sanction->getType() !== SanctionTypeEnum::WARNING,
        ));
    }

    public function openReports(): array
    {
        $grouped = [];

        foreach ($this->reports->findBy(['status' => ReportStatusEnum::OPEN], ['id' => 'DESC']) as $report) {
            $grouped[$report->getReported()->getId()][] = $report;
        }

        return array_values($grouped);
    }

    public function cases(array $filters, int $limit = 500): array
    {
        $status = $filters['status'] ?? 'open';
        $criteria = $status === 'open' ? ['status' => ReportStatusEnum::OPEN] : [];
        $cases = [];

        foreach ($this->reports->findBy($criteria, ['id' => 'DESC'], $limit) as $report) {
            if ($status === 'treated' && $report->getStatus() === ReportStatusEnum::OPEN) {
                continue;
            }

            $user = $report->getReported();
            $cases[$user->getId()] ??= ['user' => $user, 'reports' => [], 'open' => 0, 'reasons' => [], 'last' => $report->getCreatedAt()];
            $cases[$user->getId()]['reports'][] = $report;
            $cases[$user->getId()]['open'] += $report->getStatus() === ReportStatusEnum::OPEN ? 1 : 0;
            $cases[$user->getId()]['reasons'][$report->getReason()->value] = ($cases[$user->getId()]['reasons'][$report->getReason()->value] ?? 0) + 1;
        }

        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $reason = ReportReasonEnum::tryFrom((string) ($filters['reason'] ?? ''));
        $cases = array_filter($cases, fn (array $case): bool => ($search === '' || str_contains(mb_strtolower($case['user']->getUsername()), $search))
            && ($reason === null || isset($case['reasons'][$reason->value])));

        foreach ($cases as &$case) {
            arsort($case['reasons']);
            $case['history'] = count($this->history($case['user']));
        }

        unset($case);
        usort($cases, ($filters['sort'] ?? 'recent') === 'count'
            ? fn (array $a, array $b): int => [count($b['reports']), $b['last']] <=> [count($a['reports']), $a['last']]
            : fn (array $a, array $b): int => $b['last'] <=> $a['last']);

        return $cases;
    }

    public function reportsOf(User $user): array
    {
        return $this->reports->findBy(['reported' => $user], ['id' => 'DESC'], 100);
    }
}
