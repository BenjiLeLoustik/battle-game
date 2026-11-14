<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use App\Repository\ChatMessageRepository;
use App\Repository\RoomMessageRepository;
use App\Repository\SanctionRepository;
use App\Repository\UserRepository;
use App\Shared\Moderation\Enum\SanctionTypeEnum;
use App\Shared\Moderation\ModerationManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\Enum\ReportStatusEnum;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/moderation', name: 'moderation_')]
class ModerationController extends AbstractAdminController
{
    private const TABS = ['reports', 'sanctions', 'words'];
    private const STATUSES = ['open', 'treated', 'all'];
    private const SORTS = ['recent', 'count'];
    private const CONTEXT = 30;

    public function __construct(
        protected ModerationManager $moderation,
        protected UserRepository $users
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SettingManager $settings): Response
    {
        $tab = in_array($request->query->get('tab'), self::TABS, true) ? $request->query->get('tab') : 'reports';
        $filters = [
            'q' => mb_substr(trim((string) $request->query->get('q')), 0, 60),
            'reason' => ReportReasonEnum::tryFrom((string) $request->query->get('reason'))?->value ?? '',
            'status' => in_array($request->query->get('status'), self::STATUSES, true) ? $request->query->get('status') : 'open',
            'sort' => in_array($request->query->get('sort'), self::SORTS, true) ? $request->query->get('sort') : 'recent',
        ];

        return $this->render('@admin/pages/moderation/index.html.twig', [
            'tab' => $tab,
            'counts' => ['reports' => count($this->moderation->openReports()), 'sanctions' => count($this->moderation->activeSanctions())],
            'filters' => $filters,
            'cases' => $tab === 'reports' ? $this->moderation->cases($filters) : [],
            'reasons' => ReportReasonEnum::cases(),
            'statuses' => self::STATUSES,
            'sorts' => self::SORTS,
            'sanctions' => $tab === 'sanctions' ? $this->moderation->activeSanctions() : [],
            'words' => implode("\n", (array) $settings->get('filtered_words')),
        ]);
    }

    #[Route('/players/{id}', name: 'case', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function case(int $id, ChatMessageRepository $chat, RoomMessageRepository $roomMessages): Response
    {
        $target = $this->users->find($id) ?? throw $this->createNotFoundException();
        $reports = $this->moderation->reportsOf($target);
        $open = array_values(array_filter($reports, fn (Report $report): bool => $report->getStatus() === ReportStatusEnum::OPEN));
        $messages = [
            ...$chat->findBy(['user' => $target], ['id' => 'DESC'], self::CONTEXT),
            ...$roomMessages->findBy(['user' => $target], ['id' => 'DESC'], self::CONTEXT),
        ];
        usort($messages, fn (object $a, object $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('@admin/pages/moderation/case.html.twig', [
            'target' => $target,
            'reports' => $reports,
            'open' => $open,
            'reason' => ($open[0] ?? $reports[0] ?? null)?->getReason() ?? ReportReasonEnum::OTHER,
            'messages' => array_slice($messages, 0, self::CONTEXT),
            'history' => $this->moderation->history($target),
            'active' => $this->moderation->active($target),
            'types' => SanctionTypeEnum::cases(),
            'durations' => ModerationManager::DURATIONS,
            'reasons' => ReportReasonEnum::cases(),
        ]);
    }

    #[Route('/{id}/sanction', name: 'sanction', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-moderation')]
    public function sanction(int $id, Request $request): Response
    {
        $this->allow('mod.sanction');
        $user = $this->users->find($id) ?? throw $this->createNotFoundException();
        $type = SanctionTypeEnum::tryFrom((string) $request->request->get('type')) ?? SanctionTypeEnum::WARNING;
        $hours = in_array((int) $request->request->get('duration'), ModerationManager::DURATIONS, true) ? (int) $request->request->get('duration') : 72;
        $reason = ReportReasonEnum::tryFrom((string) $request->request->get('reason')) ?? ReportReasonEnum::OTHER;

        $this->moderation->sanction($user, $this->getUser(), $type, $reason, (string) $request->request->get('message'), $hours);
        $this->audit('user.sanction', $user->getUsername() . ' · ' . $type->translate());

        return $this->done('admin_moderation_case', ['id' => $id], 'admin.flash.sanction');
    }

    #[Route('/{id}/dismiss', name: 'dismiss', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-moderation')]
    public function dismiss(int $id): Response
    {
        $this->allow('mod.sanction');
        $user = $this->users->find($id) ?? throw $this->createNotFoundException();
        $this->moderation->resolve($user, ReportStatusEnum::DISMISSED);
        $this->audit('report.dismiss', $user->getUsername());

        return $this->done('admin_moderation_index', [], 'admin.flash.dismissed');
    }

    #[Route('/sanctions/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-moderation')]
    public function revoke(int $id, SanctionRepository $sanctions): Response
    {
        $this->allow('mod.sanction');
        $sanction = $sanctions->find($id) ?? throw $this->createNotFoundException();
        $this->moderation->revoke($sanction);
        $this->audit('user.revoke', $sanction->getUser()->getUsername());

        return $this->done('admin_moderation_index', ['tab' => 'sanctions']);
    }

    #[Route('/words', name: 'words', methods: ['POST'])]
    #[Csrf('admin-moderation')]
    public function words(Request $request, SettingManager $settings): Response
    {
        $this->allow('mod.words');
        $words = array_values(array_unique(array_filter(array_map(
            fn (string $word): string => mb_strtolower(trim($word)),
            preg_split('/\R/', (string) $request->request->get('words')) ?: [],
        ))));
        $settings->set('filtered_words', $words);
        $this->audit('words.update', (string) count($words));

        return $this->done('admin_moderation_index', ['tab' => 'words']);
    }
}
