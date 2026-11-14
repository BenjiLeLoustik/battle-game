<?php

namespace App\Controller\Admin;

use App\Admin\Security\AdminAccess;
use App\Entity\AdminRole;
use App\Repository\AdminRoleRepository;
use App\Repository\UserRepository;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/roles', name: 'role_')]
class RoleController extends AbstractAdminController
{
    public function __construct(
        protected AdminRoleRepository $roles,
        protected UserRepository $users
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $roles = $this->roles->findAll(['id' => 'ASC']);

        return $this->render('@admin/pages/role/index.html.twig', [
            'roles' => array_map(fn (AdminRole $role): array => ['role' => $role, 'members' => $this->users->findBy(['adminRole' => $role])], $roles),
            'total' => count(AdminAccess::all()),
            'all' => $roles,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-roles')]
    public function new(Request $request): Response
    {
        $source = $this->roles->find((int) $request->query->get('from'));
        $role = (new AdminRole())->setPermissions($source?->getPermissions() ?? ['dash.view']);

        if ($source !== null) {
            $role->setName($source->getName() . ' (copie)')->setColor($source->getColor())->setDescription($source->getDescription());
        }

        return $this->handle($request, $role);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-roles')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-roles')]
    public function delete(int $id, Request $request): Response
    {
        $role = $this->find($id);
        $target = $this->roles->find((int) $request->request->get('target'));
        $members = $this->users->findBy(['adminRole' => $role]);

        if ($role->isSystem() || ($members !== [] && ($target === null || $target->getId() === $role->getId()))) {
            $this->addFlash('error', 'admin.roles.delete_blocked');

            return $this->redirectToRoute('admin_role_index');
        }

        foreach ($members as $user) {
            $this->users->save($user->setAdminRole($target));
        }

        $this->roles->delete($role, true);
        $this->audit('role.delete', $role->getName());

        return $this->done('admin_role_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, AdminRole $role): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $name = mb_substr(trim((string) $request->request->get('name')), 0, 60);
            $color = (string) $request->request->get('color');
            $error = $name === '' ? 'admin.roles.name_required' : null;

            if ($error === null) {
                $role->setName($name)
                    ->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#B7AEFF')
                    ->setDescription(mb_substr(trim((string) $request->request->get('description')), 0, 160));

                if (!$role->isSystem()) {
                    $role->setPermissions(array_values(array_intersect(AdminAccess::all(), (array) ($request->request->all()['permissions'] ?? []))));
                }

                $this->roles->save($role, true);
                $this->audit('role.save', $role->getName());

                return $this->done('admin_role_index');
            }
        }

        return $this->render('@admin/pages/role/form.html.twig', [
            'role' => $role,
            'error' => $error,
            'groups' => AdminAccess::PERMISSIONS,
            'members' => $role->getId() === null ? [] : $this->users->findBy(['adminRole' => $role]),
        ], $error === null ? 200 : 422);
    }

    protected function find(int $id): AdminRole
    {
        return $this->roles->find($id) ?? throw $this->createNotFoundException();
    }
}
