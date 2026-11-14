<?php

namespace App\Controller\Site;

use App\Site\Search\SearchManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/search', name: 'search_')]
class SearchController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SearchManager $search): Response
    {
        $query = SearchManager::normalize((string) $request->query->get('q'));

        if ($request->wantsJson()) {
            return $this->json($search->search($query, SearchManager::SUGGESTIONS));
        }

        return $this->render('@site/pages/search/index.html.twig', [
            'query' => $query,
            'results' => $search->search($query, SearchManager::RESULTS),
            'min' => SearchManager::MIN_LENGTH,
        ]);
    }
}
