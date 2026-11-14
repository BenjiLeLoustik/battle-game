<?php

namespace App\Controller\Site;

use App\Entity\User;
use App\Shared\Exception\BattleGameException;
use App\Shared\Shop\RewardManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/rewards', name: 'reward_')]
#[IsGranted('IS_AUTHENTICATED')]
class RewardController extends AbstractController
{
    public function __construct(protected RewardManager $rewards)
    {
    }

    #[Route('/daily', name: 'daily', methods: ['POST'])]
    #[Csrf('rewards')]
    public function daily(Request $request): Response
    {
        return $this->claim($request, fn (User $user): int => $this->rewards->claimDaily($user));
    }

    #[Route('/challenges/{code}', name: 'challenge', methods: ['POST'], requirements: ['code' => '[a-z]+'])]
    #[Csrf('rewards')]
    public function challenge(string $code, Request $request): Response
    {
        return $this->claim($request, fn (User $user): int => $this->rewards->claimChallenge($user, $code));
    }

    protected function claim(Request $request, callable $action): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || $user->isGuest()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $coins = $action($user);

            if ($request->wantsJson()) {
                return $this->json(['coins' => $coins]);
            }

            $this->addFlash('success', 'site.rewards.claimed');
        } catch (BattleGameException $exception) {
            if ($request->wantsJson()) {
                return $this->json(['error' => $exception->translate()], 409);
            }

            $this->addFlash('error', $exception->getTranslationKey());
        }

        return $this->redirectToRoute('site_home_index');
    }
}
