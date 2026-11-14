<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\AdminRoleRepository;
use App\Repository\GameMemberRepository;
use App\Repository\UserRepository;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/admins', name: 'admin_')]
class AdminController extends AbstractAdminController
{
    public function __construct(
        protected UserRepository $users,
        protected AdminRoleRepository $roles,
        protected GameMemberRepository $members
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $role = $this->roles->find((int) $request->query->get('role'));
        $admins = array_filter($this->users->findAdmins(), fn (User $user): bool => $role === null || $user->getAdminRole()?->getId() === $role->getId());

        return $this->render('@admin/pages/admin/index.html.twig', [
            'admins' => array_map(fn (User $user): array => ['user' => $user, 'games' => $this->members->findBy(['user' => $user])], $admins),
            'roles' => $this->roles->findAll(['id' => 'ASC']),
            'role' => $role,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-admins')]
    public function new(Request $request): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $identifier = trim((string) $request->request->get('account'));
            $user = $this->users->findOneBy(['email' => mb_strtolower($identifier)]) ?? $this->users->findOneBy(['username' => ltrim($identifier, '@')]);
            $error = match (true) {
                $user === null || $user->isGuest() => 'admin.admins.not_found',
                $user->isAdmin() => 'admin.admins.already',
                default => null,
            };

            if ($error === null) {
                $this->save($user, $request);
                $this->audit('admin.save', $user->getUsername());

                return $this->done('admin_admin_index');
            }
        }

        return $this->form(null, $error);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-admins')]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->find($id);

        if ($request->getMethod() === 'POST') {
            $this->save($user, $request);
            $this->audit('admin.save', $user->getUsername());

            return $this->done('admin_admin_index');
        }

        return $this->form($user, null);
    }

    #[Route('/{id}/remove', name: 'remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-admins')]
    public function remove(int $id): Response
    {
        $user = $this->find($id);

        if ($user->getId() === $this->getUser()->getId()) {
            return $this->redirectToRoute('admin_admin_index');
        }

        foreach ($this->members->findBy(['user' => $user]) as $member) {
            $this->members->delete($member);
        }

        $user->setRoles(array_values(array_diff($user->getRoles(), ['ROLE_ADMIN', 'ROLE_USER'])))->setAdminRole(null);
        $this->users->save($user, true);
        $this->audit('admin.remove', $user->getUsername());

        return $this->done('admin_admin_index', [], 'admin.flash.deleted');
    }

    protected function save(User $user, Request $request): void
    {
        $role = $this->roles->find((int) $request->request->get('role')) ?? $this->roles->findOneBy(['system' => true]);
        $roles = array_values(array_unique([...array_diff($user->getRoles(), ['ROLE_USER']), 'ROLE_ADMIN']));
        $this->users->save($user->setRoles($roles)->setAdminRole($role), true);
    }

    protected function form(?User $user, ?string $error): Response
    {
        return $this->render('@admin/pages/admin/form.html.twig', [
            'member' => $user,
            'error' => $error,
            'roles' => array_map(fn ($role): array => ['role' => $role, 'count' => $this->users->count(['adminRole' => $role])], $this->roles->findAll(['id' => 'ASC'])),
            'games' => $user === null ? [] : $this->members->findBy(['user' => $user]),
        ], $error === null ? 200 : 422);
    }

    protected function find(int $id): User
    {
        $user = $this->users->find($id);

        return $user !== null && $user->isAdmin() ? $user : throw $this->createNotFoundException();
    }
}
