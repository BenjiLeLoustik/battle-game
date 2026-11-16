<?php

namespace App\Controller\Site;

use App\Repository\CategoryRepository;
use App\Repository\GameRepository;
use App\Shared\Game\GameMatchManager;
use App\Shared\Setting\SettingManager;
use App\Site\Onboarding\OnboardingManager;
use App\Site\Security\Form\GuestForm;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/', name: 'home_')]
class HomeController extends AbstractController
{
    private const PAGE_SIZE = 12;

    public function __construct(
        protected GameRepository $games,
        protected GameMatchManager $matches,
        protected CategoryRepository $categories
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SettingManager $settings, OnboardingManager $onboarding): Response
    {
        [$games, $more] = $this->games->findLivePage(null, 1, self::PAGE_SIZE);

        return $this->render('@site/pages/home/index.html.twig', [
            'featured' => $this->games->findFeatured(true),
            'games' => $games,
            'more' => $more,
            'online' => $this->matches->online(),
            'categories' => $this->categories->findOrdered(true),
            'guest' => $this->getUser() === null && $settings->get('guests') ? $this->createForm(GuestForm::class, null, ['action' => $this->generateUrl('site_room_join')]) : null,
            'onboarding' => $onboarding->build($this->getUser()),
        ]);
    }

    #[Route('/feed', name: 'feed', methods: ['GET'])]
    public function feed(Request $request): Response
    {
        $slug = (string) $request->query->get('category', '');
        $category = $slug === '' ? null : $this->categories->findBySlug($slug);
        $page = max(1, (int) $request->query->get('page', 1));
        [$games, $more] = $slug !== '' && $category === null ? [[], false] : $this->games->findLivePage($category, $page, self::PAGE_SIZE);

        return $this->json([
            'html' => $this->renderView('@site/pages/home/feed.html.twig', ['games' => $games, 'online' => $this->matches->online()]),
            'next' => $more ? $page + 1 : null,
        ]);
    }
}
