<?php

namespace App\Controller\Admin;

use App\Admin\Reset\BackupManager;
use App\Admin\Reset\ResetManager;
use App\Shared\Setting\SettingManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/reset', name: 'reset_')]
class ResetController extends AbstractAdminController
{
    public const CONFIRM = 'RÉINITIALISER';
    public const RESTORE = 'RESTAURER';

    public function __construct(
        protected ResetManager $resets,
        protected BackupManager $backups,
        protected SettingManager $settings,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/reset/index.html.twig', [
            'blocks' => $this->resets->blocks(),
            'backups' => $this->backups->list(),
            'confirm' => self::CONFIRM,
            'restore' => self::RESTORE,
            'auto' => ['enabled' => (bool) $this->settings->get('backup_auto'), 'files' => (bool) $this->settings->get('backup_files'), 'keep' => (int) $this->settings->get('backup_keep')],
            'scheduler' => $this->scheduler(),
        ]);
    }

    #[Route('/auto', name: 'auto', methods: ['POST'])]
    #[Csrf('admin-reset')]
    public function auto(Request $request): Response
    {
        $this->settings->set('backup_auto', $request->request->get('backup_auto') !== null);
        $this->settings->set('backup_files', $request->request->get('backup_files') !== null);
        $this->settings->set('backup_keep', max(1, min(60, (int) $request->request->get('backup_keep'))));
        $this->audit('reset.auto');

        return $this->done('admin_reset_index');
    }

    #[Route('/backups/files', name: 'backup_files', methods: ['POST'])]
    #[Csrf('admin-reset')]
    public function backupFiles(): Response
    {
        $name = $this->backups->createFiles();
        $this->audit('reset.backup', $name);

        return $this->done('admin_reset_index', [], 'admin.reset.flash.backup');
    }

    protected function scheduler(): array
    {
        $heartbeat = $this->rootPath . '/var/scheduler/heartbeat';
        $last = is_file($heartbeat) ? filemtime($heartbeat) : null;

        return ['running' => $last !== null && time() - $last < 180, 'last' => $last];
    }

    #[Route('', name: 'run', methods: ['POST'])]
    #[Csrf('admin-reset')]
    public function run(Request $request): Response
    {
        $blocks = array_values(array_intersect(array_keys(ResetManager::BLOCKS), (array) $request->request->all()['blocks'] ?? []));

        if ($blocks === [] || trim((string) $request->request->get('confirm')) !== self::CONFIRM) {
            $this->addFlash('error', 'admin.reset.flash.invalid');

            return $this->redirectToRoute('admin_reset_index');
        }

        try {
            $backup = $this->backups->create('avant-reinitialisation');
        } catch (Throwable) {
            $this->addFlash('error', 'admin.reset.flash.backup_failed');

            return $this->redirectToRoute('admin_reset_index');
        }

        $deleted = $this->resets->reset($blocks);
        $this->audit('reset.run', implode(', ', $blocks) . ' · ' . array_sum($deleted) . ' · ' . $backup);

        return $this->done('admin_reset_index', [], 'admin.reset.flash.done');
    }

    #[Route('/backups', name: 'backup', methods: ['POST'])]
    #[Csrf('admin-reset')]
    public function backup(): Response
    {
        $name = $this->backups->create();
        $this->audit('reset.backup', $name);

        return $this->done('admin_reset_index', [], 'admin.reset.flash.backup');
    }

    #[Route('/backups/{name}', name: 'download', methods: ['GET'], requirements: ['name' => '[A-Za-z0-9.-]+'])]
    public function download(string $name): Response
    {
        try {
            return $this->fileResponse($this->backups->path($name), $name);
        } catch (Throwable) {
            throw $this->createNotFoundException();
        }
    }

    #[Route('/backups/{name}/restore', name: 'restore', methods: ['POST'], requirements: ['name' => '[A-Za-z0-9.-]+'])]
    #[Csrf('admin-reset')]
    public function restore(string $name, Request $request): Response
    {
        if (trim((string) $request->request->get('confirm')) !== self::RESTORE) {
            $this->addFlash('error', 'admin.reset.flash.invalid');

            return $this->redirectToRoute('admin_reset_index');
        }

        try {
            if (!str_ends_with($this->backups->path($name), '.sql')) {
                throw $this->createNotFoundException();
            }

            $safety = $this->backups->create('avant-restauration');
            $this->backups->restore($name);
        } catch (Throwable) {
            $this->addFlash('error', 'admin.reset.flash.restore_failed');

            return $this->redirectToRoute('admin_reset_index');
        }

        $this->audit('reset.restore', $name . ' · ' . $safety);

        return $this->done('admin_reset_index', [], 'admin.reset.flash.restored');
    }

    #[Route('/backups/{name}/delete', name: 'delete', methods: ['POST'], requirements: ['name' => '[A-Za-z0-9.-]+'])]
    #[Csrf('admin-reset')]
    public function delete(string $name): Response
    {
        try {
            $this->backups->delete($name);
            $this->audit('reset.delete', $name);
        } catch (Throwable) {
            throw $this->createNotFoundException();
        }

        return $this->done('admin_reset_index', [], 'admin.flash.deleted');
    }
}
