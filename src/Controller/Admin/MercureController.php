<?php

namespace App\Controller\Admin;

use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;
use Throwable;

#[Route('/realtime', name: 'mercure_')]
class MercureController extends AbstractAdminController
{
    private const ERRORS = 10;

    public function __construct(
        protected MercureManagerInterface $mercure,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
        #[Autowire(env: 'MERCURE_PUBLIC_URL')]
        protected ?string $hub = null
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/mercure/index.html.twig', [
            'hub' => $this->hub,
            'status' => $this->ping(),
            'errors' => $this->errors(),
        ]);
    }

    #[Route('/publish', name: 'publish', methods: ['POST'])]
    #[Csrf('admin-mercure')]
    public function publish(Request $request): Response
    {
        $topic = trim((string) $request->request->get('topic')) ?: 'admin/test';
        $data = json_decode((string) $request->request->get('data'), true) ?? ['message' => (string) $request->request->get('data')];

        try {
            $this->mercure->publish(Update::json($topic, $data, $request->request->get('private') !== null));
            $this->audit('mercure.test', $topic);
            $published = true;
        } catch (Throwable) {
            $published = false;
        }

        if ($request->wantsJson()) {
            return $this->json(['ok' => $published], $published ? 200 : 502);
        }

        $this->addFlash($published ? 'success' : 'error', $published ? 'admin.flash.published' : 'admin.mercure.publish_failed');

        return $this->redirectToRoute('admin_mercure_index');
    }

    protected function ping(): array
    {
        try {
            $start = microtime(true);
            $this->mercure->publish(Update::json('admin/ping', ['at' => time()]));

            return ['ok' => true, 'latency' => (int) round((microtime(true) - $start) * 1000)];
        } catch (Throwable $exception) {
            return ['ok' => false, 'latency' => null, 'error' => $exception->getMessage()];
        }
    }

    protected function errors(): array
    {
        $file = $this->rootPath . '/var/log/framework.log';

        if (!is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        fseek($handle, max(0, filesize($file) - 200000));
        $lines = array_filter(explode("\n", (string) stream_get_contents($handle)), fn (string $line): bool => stripos($line, 'mercure') !== false && preg_match('/\.(ERROR|WARNING|CRITICAL)/', $line));
        fclose($handle);

        return array_slice(array_reverse(array_values($lines)), 0, self::ERRORS);
    }
}
