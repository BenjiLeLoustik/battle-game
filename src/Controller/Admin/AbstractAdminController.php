<?php

namespace App\Controller\Admin;

use App\Admin\Audit\AuditLogger;
use App\Admin\Security\AdminAccess;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;

abstract class AbstractAdminController extends AbstractController
{
    protected function can(string $permission): bool
    {
        return $this->get(AdminAccess::class)->can($this->getUser(), $permission);
    }

    protected function allow(string $permission): void
    {
        if (!$this->can($permission)) {
            throw $this->createAccessDeniedException();
        }
    }

    protected function audit(string $action, string $detail = ''): void
    {
        $this->get(AuditLogger::class)->log($this->getUser(), $action, $detail);
    }

    protected function saveTranslations(TranslatableInterface $entity, Request $request): void
    {
        $data = $request->request->get('translations');
        $this->get(ContentTranslationManager::class)->save($entity, is_array($data) ? $data : []);
    }

    protected function deleteTranslations(TranslatableInterface $entity): void
    {
        $this->get(ContentTranslationManager::class)->delete($entity);
    }

    protected function done(string $route, array $parameters = [], string $message = 'admin.flash.saved'): Response
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute($route, $parameters);
    }
}
