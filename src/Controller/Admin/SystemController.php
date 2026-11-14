<?php

namespace App\Controller\Admin;

use App\Admin\Audit\AuditLogger;
use App\Admin\Dashboard\DashboardManager;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Setting\SettingManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

#[Route('/system', name: 'system_')]
class SystemController extends AbstractAdminController
{
    private const TOGGLES = ['guests', 'registration', 'public_rooms'];

    public function __construct(
        protected SettingManager $settings,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
        #[Autowire(env: 'DISCORD_CLIENT_ID')]
        protected ?string $discordClient = null
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(MaintenanceManager $maintenance, DashboardManager $dashboard, AuditLogger $logs): Response
    {
        return $this->render('@admin/pages/system/index.html.twig', [
            'toggles' => array_combine(self::TOGGLES, array_map(fn (string $key): bool => (bool) $this->settings->get($key), self::TOGGLES)),
            'maintenances' => count($maintenance->open()),
            'discord' => $this->discordClient ? substr($this->discordClient, 0, 6) . '…' : null,
            'services' => $dashboard->services(),
            'storage' => [
                'uploads' => $this->size($this->rootPath . '/public/uploads'),
                'games' => $this->size($this->rootPath . '/var/games'),
                'cache' => $this->size($this->rootPath . '/var/cache'),
            ],
            'logs' => $logs->latest(30),
            'periods' => array_keys(AuditLogger::PERIODS),
        ]);
    }

    #[Route('/audit/export', name: 'audit_export', methods: ['GET'])]
    public function exportAudit(Request $request, AuditLogger $logs): Response
    {
        $period = array_key_exists((string) $request->query->get('period'), AuditLogger::PERIODS) ? (string) $request->query->get('period') : 'all';
        $this->audit('audit.export', $period);

        return new Response($logs->csv($period), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="battlegame-audit-%s-%s.csv"', $period, date('Y-m-d')),
        ]);
    }

    #[Route('/settings', name: 'settings', methods: ['POST'])]
    #[Csrf('admin-system')]
    public function settings(Request $request): Response
    {
        foreach (self::TOGGLES as $key) {
            $this->settings->set($key, $request->request->get($key) !== null);
        }

        $this->audit('setting.update', implode(', ', array_filter(self::TOGGLES, fn (string $key): bool => $request->request->get($key) !== null)));

        return $this->done('admin_system_index');
    }

    #[Route('/cache', name: 'cache', methods: ['POST'])]
    #[Csrf('admin-system')]
    public function clearCache(): Response
    {
        $directory = $this->rootPath . '/var/cache';

        if (is_dir($directory)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($items as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
        }

        $this->audit('cache.clear');

        return $this->done('admin_system_index', [], 'admin.flash.cache');
    }

    protected function size(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
