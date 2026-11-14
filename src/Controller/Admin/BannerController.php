<?php

namespace App\Controller\Admin;

use App\Entity\Banner;
use App\Entity\BannerList;
use App\Repository\BannerListRepository;
use App\Repository\BannerRepository;
use App\Shared\Banner\BannerManager;
use App\Shared\Banner\Enum\BannerAudienceEnum;
use App\Shared\Banner\Enum\BannerPagesEnum;
use App\Shared\Banner\Enum\BannerTypeEnum;
use App\Shared\Maintenance\MaintenanceManager;
use DateTime;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/banners', name: 'banner_')]
class BannerController extends AbstractAdminController
{
    private const DATE_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        protected BannerManager $manager,
        protected BannerRepository $banners,
        protected BannerListRepository $lists
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, MaintenanceManager $maintenance): Response
    {
        $live = $this->manager->liveList();
        $list = $this->lists->find((int) $request->query->get('list')) ?? $live ?? $this->lists->findOneBy([]);

        return $this->render('@admin/pages/banner/index.html.twig', [
            'live' => $live,
            'liveBanners' => $live === null ? [] : array_filter($this->manager->forList($live), fn (Banner $banner): bool => $banner->isLive()),
            'maintenances' => $maintenance->announcements(),
            'lists' => array_map(fn (BannerList $item): array => ['list' => $item, 'count' => $this->banners->count(['list' => $item])], $this->lists->findAll(['id' => 'ASC'])),
            'list' => $list,
            'rows' => $list === null ? [] : $this->manager->forList($list),
            'now' => new DateTime(),
        ]);
    }

    #[Route('/lists', name: 'list_new', methods: ['POST'])]
    #[Csrf('admin-banners')]
    public function newList(Request $request): Response
    {
        $name = mb_substr(trim((string) $request->request->get('name')), 0, 60);

        if ($name !== '') {
            $list = (new BannerList())->setName($name);
            $this->lists->save($list, true);
            $this->audit('banner.list', $name);

            return $this->redirectToRoute('admin_banner_index', ['list' => $list->getId()]);
        }

        return $this->redirectToRoute('admin_banner_index');
    }

    #[Route('/lists/{id}/live', name: 'list_live', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function live(int $id): Response
    {
        $list = $this->lists->find($id) ?? throw $this->createNotFoundException();
        $this->manager->publish($list);
        $this->audit('banner.list', $list->getName());

        return $this->done('admin_banner_index', ['list' => $id]);
    }

    #[Route('/lists/{id}/schedule', name: 'list_schedule', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function schedule(int $id, Request $request): Response
    {
        $list = $this->lists->find($id) ?? throw $this->createNotFoundException();
        $date = DateTime::createFromFormat(self::DATE_FORMAT, (string) $request->request->get('scheduled_at')) ?: null;

        if ($request->request->get('cancel') === null && ($date === null || $date <= new DateTime())) {
            $this->addFlash('error', 'admin.banners.schedule_invalid');

            return $this->redirectToRoute('admin_banner_index', ['list' => $id]);
        }

        $this->manager->schedule($list, $request->request->get('cancel') === null ? $date : null);
        $this->audit('banner.schedule', $list->getName() . ($list->getScheduledAt() ? ' · ' . $list->getScheduledAt()->format('d/m/Y H:i') : ''));

        return $this->done('admin_banner_index', ['list' => $id]);
    }

    #[Route('/lists/{id}/delete', name: 'list_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function deleteList(int $id): Response
    {
        $list = $this->lists->find($id) ?? throw $this->createNotFoundException();

        if (!$list->isLive()) {
            foreach ($this->banners->findBy(['list' => $list]) as $banner) {
                $this->deleteTranslations($banner);
            }

            $this->lists->delete($list, true);
            $this->audit('banner.delete', $list->getName());
        }

        return $this->redirectToRoute('admin_banner_index');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-banners')]
    public function new(Request $request): Response
    {
        $list = $this->lists->find((int) $request->query->get('list')) ?? $this->manager->liveList();

        if ($list === null) {
            return $this->redirectToRoute('admin_banner_index');
        }

        return $this->handle($request, (new Banner())->setList($list)->setPosition($this->banners->count(['list' => $list])));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function toggle(int $id): Response
    {
        $banner = $this->find($id);
        $this->banners->save($banner->setActive(!$banner->isActive()), true);

        return $this->redirectToRoute('admin_banner_index', ['list' => $banner->getList()->getId()]);
    }

    #[Route('/{id}/move/{direction}', name: 'move', methods: ['POST'], requirements: ['id' => '\d+', 'direction' => 'up|down'])]
    #[Csrf('admin-banners')]
    public function move(int $id, string $direction): Response
    {
        $banner = $this->find($id);
        $this->manager->move($banner, $direction);

        return $this->redirectToRoute('admin_banner_index', ['list' => $banner->getList()->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-banners')]
    public function delete(int $id): Response
    {
        $banner = $this->find($id);
        $list = $banner->getList()->getId();
        $this->deleteTranslations($banner);
        $this->banners->delete($banner, true);
        $this->audit('banner.delete', $banner->getText());

        return $this->done('admin_banner_index', ['list' => $list], 'admin.flash.deleted');
    }

    protected function handle(Request $request, Banner $banner): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $text = mb_substr(trim((string) ($data['text'] ?? '')), 0, 160);
            $url = trim((string) ($data['url'] ?? ''));
            $error = $text === '' ? 'admin.banners.text_required' : ($url !== '' && !str_starts_with($url, '/') && !filter_var($url, FILTER_VALIDATE_URL) ? 'admin.banners.url_invalid' : null);

            if ($error === null) {
                $this->banners->save($banner
                    ->setList($this->lists->find((int) ($data['list'] ?? 0)) ?? $banner->getList())
                    ->setText($text)
                    ->setCta(mb_substr(trim((string) ($data['cta'] ?? '')), 0, 40) ?: null)
                    ->setUrl($url ?: null)
                    ->setType(BannerTypeEnum::tryFrom((string) ($data['type'] ?? '')) ?? BannerTypeEnum::INFO)
                    ->setAudience(BannerAudienceEnum::tryFrom((string) ($data['audience'] ?? '')) ?? BannerAudienceEnum::ALL)
                    ->setPages(BannerPagesEnum::tryFrom((string) ($data['pages'] ?? '')) ?? BannerPagesEnum::ALL)
                    ->setStartsAt(DateTime::createFromFormat(self::DATE_FORMAT, (string) ($data['starts_at'] ?? '')) ?: null)
                    ->setEndsAt(DateTime::createFromFormat(self::DATE_FORMAT, (string) ($data['ends_at'] ?? '')) ?: null)
                    ->setDismissible(isset($data['dismissible']))
                    ->setActive(($data['publish'] ?? '') === '1'), true);
                $this->saveTranslations($banner, $request);
                $this->audit('banner.save', $text);

                return $this->done('admin_banner_index', ['list' => $banner->getList()->getId()]);
            }
        }

        return $this->render('@admin/pages/banner/form.html.twig', [
            'banner' => $banner,
            'error' => $error,
            'lists' => $this->lists->findAll(['id' => 'ASC']),
            'types' => BannerTypeEnum::cases(),
            'audiences' => BannerAudienceEnum::cases(),
            'pages' => BannerPagesEnum::cases(),
        ], $error === null ? 200 : 422);
    }

    protected function find(int $id): Banner
    {
        return $this->banners->find($id) ?? throw $this->createNotFoundException();
    }
}
