<?php

namespace App\Controller\Admin;

use App\Admin\Studio\NewGameRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\GameRepository;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/categories', name: 'category_')]
class CategoryController extends AbstractAdminController
{
    public const COLORS = ['#2EE6D6', '#A78BFA', '#FF3DCB', '#4ADE80', '#FFC94A', '#FF9F43', '#60A5FA', '#FF5470'];

    public function __construct(
        protected CategoryRepository $categories,
        protected GameRepository $games
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/category/index.html.twig', [
            'rows' => array_map(fn (Category $category): array => ['category' => $category, 'games' => $this->games->count(['category' => $category])], $this->categories->findOrdered()),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-categories')]
    public function new(Request $request): Response
    {
        return $this->handle($request, (new Category())->setPosition(count($this->categories->findAll())));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-categories')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/move/{direction}', name: 'move', methods: ['POST'], requirements: ['id' => '\d+', 'direction' => 'up|down'])]
    #[Csrf('admin-categories')]
    public function move(int $id, string $direction): Response
    {
        $list = $this->categories->findOrdered();
        $index = array_search($this->find($id), $list, true);
        $swap = $index + ($direction === 'up' ? -1 : 1);

        if (isset($list[$swap])) {
            [$list[$index], $list[$swap]] = [$list[$swap], $list[$index]];
        }

        foreach ($list as $position => $category) {
            $this->categories->save($category->setPosition($position));
        }

        $this->categories->getOrm()->flush();

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-categories')]
    public function toggle(int $id): Response
    {
        $category = $this->find($id);
        $this->categories->save($category->setActive(!$category->isActive()), true);
        $this->audit('category.save', $category->getName());

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-categories')]
    public function delete(int $id): Response
    {
        $category = $this->find($id);

        if ($this->games->count(['category' => $category]) > 0) {
            $this->addFlash('error', 'admin.categories.used');

            return $this->redirectToRoute('admin_category_index');
        }

        $this->deleteTranslations($category);
        $this->categories->delete($category, true);
        $this->audit('category.delete', $category->getName());

        return $this->done('admin_category_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, Category $category): Response
    {
        $locked = $category->getId() !== null && $this->games->count(['category' => $category]) > 0;
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $name = mb_substr(trim((string) $request->request->get('name')), 0, 60);
            $slug = $locked ? $category->getSlug() : NewGameRequest::slugify((string) ($request->request->get('slug') ?: $name));
            $color = (string) $request->request->get('color');
            $other = $this->categories->findBySlug($slug);
            $errors = array_filter([
                'name' => $name === '' ? 'admin.categories.name_required' : null,
                'slug' => $slug === '' || ($other !== null && $other !== $category) ? 'admin.categories.slug_taken' : null,
            ]);

            if ($errors === []) {
                $this->categories->save($category
                    ->setName($name)
                    ->setSlug($slug)
                    ->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : self::COLORS[0])
                    ->setActive($request->request->get('active') !== null), true);
                $this->saveTranslations($category, $request);
                $this->audit('category.save', $name);

                return $this->done('admin_category_index');
            }
        }

        return $this->render('@admin/pages/category/form.html.twig', [
            'category' => $category,
            'locked' => $locked,
            'errors' => $errors,
            'colors' => self::COLORS,
        ], $errors === [] ? 200 : 422);
    }

    protected function find(int $id): Category
    {
        return $this->categories->find($id) ?? throw $this->createNotFoundException();
    }
}
