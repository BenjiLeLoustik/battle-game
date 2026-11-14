<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Repository\PageRepository;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Page\Enum\PageFooterEnum;
use App\Shared\Page\Enum\PageFormatEnum;
use App\Shared\Page\PageManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/pages', name: 'page_')]
class PageController extends AbstractAdminController
{
    public function __construct(
        protected PageRepository $pages,
        protected PageManager $manager,
        protected ContentTranslationManager $contents,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/page/index.html.twig', [
            'pages' => $this->pages->findOrdered(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-pages')]
    public function new(Request $request): Response
    {
        return $this->handle($request, new Page());
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-pages')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-pages')]
    public function toggle(int $id): Response
    {
        $page = $this->find($id);
        $this->pages->save($page->setPublished(!$page->isPublished()), true);
        $this->audit('page.save', $page->getUrl());

        return $this->done('admin_page_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-pages')]
    public function delete(int $id): Response
    {
        $page = $this->find($id);
        $this->deleteTranslations($page);
        $this->manager->deleteStrings($page);
        $this->pages->delete($page, true);
        $this->audit('page.delete', $page->getUrl());

        return $this->done('admin_page_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, Page $page): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 120);
            $path = PageManager::normalize((string) (($data['path'] ?? '') ?: $title));
            $other = $this->pages->findByPath($path);
            $errors = array_filter([
                'title' => $title === '' ? 'admin.pages.title_required' : null,
                'path' => $other !== null && $other !== $page ? 'admin.pages.path_taken' : (!$this->manager->available($path) ? 'admin.pages.path_reserved' : null),
            ]);

            $page
                ->setTitle($title)
                ->setPath($path)
                ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 160))
                ->setFormat(PageFormatEnum::tryFrom((string) ($data['format'] ?? '')) ?? PageFormatEnum::MARKDOWN)
                ->setContent((string) ($data['content'] ?? ''))
                ->setStyle((string) ($data['style'] ?? ''))
                ->setFooter(PageFooterEnum::tryFrom((string) ($data['footer'] ?? '')) ?? PageFooterEnum::NONE)
                ->setPosition((int) ($data['position'] ?? 0))
                ->setPublished(isset($data['published']));

            if ($errors === []) {
                $this->pages->save($page, true);
                $this->saveTranslations($page, $request);
                $this->manager->saveStrings($page, is_array($data['strings'] ?? null) ? $data['strings'] : []);
                $this->audit('page.save', $page->getUrl());

                return $this->done('admin_page_index');
            }
        }

        return $this->render('@admin/pages/page/form.html.twig', [
            'page' => $page,
            'errors' => $errors,
            'formats' => PageFormatEnum::cases(),
            'footers' => PageFooterEnum::cases(),
            'strings' => $request->getMethod() === 'POST' && is_array($request->request->all()['strings'] ?? null) ? $request->request->all()['strings'] : $this->manager->strings($page),
            'locales' => $this->contents->locales(),
        ], $errors === [] ? 200 : 422);
    }

    protected function find(int $id): Page
    {
        return $this->pages->find($id) ?? throw $this->createNotFoundException();
    }
}
