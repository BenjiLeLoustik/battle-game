<?php

namespace App\Controller\Admin;

use App\Admin\User\UserAdminRequest;
use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\GameResultRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Shared\Moderation\Enum\SanctionTypeEnum;
use App\Shared\Moderation\ModerationManager;
use App\Shared\Pagination\Pagination;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\FriendManager;
use App\Shared\User\LevelManager;
use App\Site\Account\AccountManager;
use App\Site\Account\PasswordResetManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/users', name: 'user_')]
class UserController extends AbstractAdminController
{
    private const STATUSES = ['all', 'active', 'suspension', 'ban'];
    private const AUTHS = ['all', 'both', 'email', 'discord'];

    public function __construct(
        protected UserRepository $users,
        protected ModerationManager $moderation,
        protected CountryRepository $countries
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q')),
            'status' => in_array($request->query->get('status'), self::STATUSES, true) ? $request->query->get('status') : 'all',
            'auth' => in_array($request->query->get('auth'), self::AUTHS, true) ? $request->query->get('auth') : 'all',
        ];
        $page = max(1, (int) $request->query->get('page', 1));
        [$users, $total] = $this->users->search($filters, $page, Pagination::SIZE);

        return $this->render('@admin/pages/user/index.html.twig', [
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'auths' => self::AUTHS,
            'pager' => new Pagination(array_map($this->row(...), $users), $page, $total),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        $this->allow('users.edit');
        $lines = [['id', 'username', 'email', 'discord', 'created_at', 'seen_at']];

        foreach ($this->users->findBy(['guest' => false], ['id' => 'ASC']) as $user) {
            $lines[] = [$user->getId(), $user->getUsername(), $user->getEmail(), $user->getDiscordId() ? 'yes' : 'no', $user->getCreatedAt()?->format('Y-m-d H:i'), $user->getSeenAt()?->format('Y-m-d H:i')];
        }

        $csv = implode("\n", array_map(fn (array $line): string => implode(';', array_map(fn (mixed $value): string => '"' . str_replace('"', '""', (string) $value) . '"', $line)), $lines));
        $response = new Response("\u{FEFF}" . $csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="battlegame-users.csv"');
        $this->audit('user.export');

        return $response;
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-user')]
    public function new(Request $request, PasswordResetManager $resets): Response
    {
        $this->allow('users.edit');

        return $this->handle($request, new User(), $resets);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, GameResultRepository $results, ReportRepository $reports, FriendManager $friends, LevelManager $levels, AccountManager $accounts): Response
    {
        $user = $this->find($id);
        $totals = $results->totals($user);

        return $this->render('@admin/pages/user/show.html.twig', $this->row($user) + [
            'stats' => [
                'games' => (int) $totals['played'],
                'wins' => (int) $totals['won'],
                'level' => $levels->level((int) $totals['points']),
                'friends' => count($friends->friends($user)),
                'reports' => $reports->count(['reported' => $user]),
            ],
            'sanctions' => $this->moderation->history($user),
            'recent' => $results->recent($user, 10),
            'devices' => $accounts->devices($user, null),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-user')]
    public function edit(int $id, Request $request, PasswordResetManager $resets): Response
    {
        $this->allow('users.edit');

        return $this->handle($request, $this->find($id), $resets);
    }

    #[Route('/{id}/reset', name: 'reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-user')]
    public function reset(int $id, PasswordResetManager $resets): Response
    {
        $this->allow('users.edit');
        $user = $this->find($id);
        $resets->send($user);
        $this->audit('user.reset', $user->getUsername());

        return $this->done('admin_user_show', ['id' => $id], 'admin.flash.reset_sent');
    }

    #[Route('/{id}/logout', name: 'logout', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-user')]
    public function logout(int $id, AccountManager $accounts): Response
    {
        $this->allow('users.edit');
        $user = $this->find($id);
        $accounts->revokeOthers($user, null);
        $this->audit('user.logout', $user->getUsername());

        return $this->done('admin_user_show', ['id' => $id], 'admin.flash.devices');
    }

    #[Route('/{id}/lift', name: 'lift', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-user')]
    public function lift(int $id): Response
    {
        $this->allow('users.suspend');
        $user = $this->find($id);

        foreach ($this->moderation->active($user) as $sanction) {
            $this->moderation->revoke($sanction);
        }

        $this->audit('user.revoke', $user->getUsername());

        return $this->done('admin_user_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-user')]
    public function delete(int $id, Request $request): Response
    {
        $this->allow('users.delete');
        $user = $this->find($id);

        if ($request->request->get('confirm') !== 'SUPPRIMER' || $user->getId() === $this->getUser()->getId()) {
            return $this->redirectToRoute('admin_user_show', ['id' => $id]);
        }

        $this->users->delete($user, true);
        $this->audit('user.delete', $user->getUsername());

        return $this->done('admin_user_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, User $user, PasswordResetManager $resets): Response
    {
        $creating = $user->getId() === null;
        $status = $creating ? 'active' : $this->status($user);
        $data = $request->getMethod() === 'POST' ? UserAdminRequest::fromArray($request->request->all(), $this->countries->codes()) : UserAdminRequest::fromUser($user, $status);
        $errors = $request->getMethod() === 'POST' ? $data->errors($creating ? null : $user, fn (string $field, string $value): bool => $this->users->findOneBy([$field => $value]) !== null) : [];

        if ($request->getMethod() === 'POST' && $errors === []) {
            $data->applyTo($user);

            if ($creating) {
                $user->setLastName('');
            }

            $this->users->save($user, true);
            $this->applyStatus($user, $status, $data);

            if ($creating) {
                $resets->send($user);
            }

            $this->audit($creating ? 'user.create' : 'user.update', $user->getUsername());

            return $this->done('admin_user_show', ['id' => $user->getId()]);
        }

        return $this->render('@admin/pages/user/form.html.twig', [
            'user' => $user,
            'creating' => $creating,
            'data' => $data,
            'errors' => $errors,
            'statuses' => UserAdminRequest::STATUSES,
            'durations' => ModerationManager::DURATIONS,
            'reasons' => ReportReasonEnum::cases(),
            'countries' => $this->countries->findOrdered(),
        ], $errors === [] ? 200 : 422);
    }

    protected function applyStatus(User $user, string $previous, UserAdminRequest $data): void
    {
        if ($data->status === $previous || !$this->can('users.suspend')) {
            return;
        }

        foreach ($this->moderation->active($user) as $sanction) {
            if (in_array($sanction->getType(), [SanctionTypeEnum::SUSPENSION, SanctionTypeEnum::BAN], true)) {
                $this->moderation->revoke($sanction);
            }
        }

        if ($data->sanctionType() !== null) {
            $this->moderation->sanction($user, $this->getUser(), $data->sanctionType(), $data->reason, $data->message, $data->duration);
            $this->audit('user.sanction', $user->getUsername() . ' · ' . $data->sanctionType()->translate());
        }
    }

    protected function row(User $user): array
    {
        return [
            'user' => $user,
            'status' => $this->status($user),
            'lockout' => $this->moderation->lockout($user),
            'games' => (int) $this->users->getOrm()->getConnection()->fetchOne('SELECT COUNT(*) FROM game_result WHERE user_id = :user', ['user' => $user->getId()]),
        ];
    }

    protected function status(User $user): string
    {
        return $this->moderation->lockout($user)?->getType()->value ?? 'active';
    }

    protected function find(int $id): User
    {
        return $this->users->find($id) ?? throw $this->createNotFoundException();
    }
}
