<?php

namespace App\Controller\Admin;

use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Yaml\YamlManager;

#[Route('/docs', name: 'docs_')]
class DocsController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $docs = $this->docs();

        return $this->render('@admin/pages/docs/index.html.twig', [
            'docs' => $docs,
            'groups' => $this->groups($docs),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[a-z0-9-]+'])]
    public function show(string $id): Response
    {
        $docs = $this->docs();
        $sections = array_values($docs['sections'] ?? []);
        $index = array_search($id, array_column($sections, 'id'), true);

        if ($index === false) {
            throw $this->createNotFoundException();
        }

        return $this->render('@admin/pages/docs/show.html.twig', [
            'docs' => $docs,
            'groups' => $this->groups($docs),
            'section' => $sections[$index],
            'previous' => $sections[$index - 1] ?? null,
            'next' => $sections[$index + 1] ?? null,
        ]);
    }

    protected function docs(): array
    {
        return (new YamlManager())->parseFile($this->rootPath . '/translations/docs.fr.yaml')['docs'] ?? [];
    }

    protected function groups(array $docs): array
    {
        $groups = [];

        foreach ($docs['sections'] ?? [] as $section) {
            $section['search'] = $this->searchable($section);
            $groups[$section['group'] ?? 'help'][] = $section;
        }

        return $groups;
    }

    protected function searchable(array $section): string
    {
        $words = [];
        array_walk_recursive($section, function (mixed $value) use (&$words): void {
            if (is_string($value)) {
                $words[] = $value;
            }
        });

        return mb_strtolower(str_replace(['`', '*'], '', implode(' ', $words)));
    }
}
