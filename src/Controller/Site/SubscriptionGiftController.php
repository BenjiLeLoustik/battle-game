<?php

namespace App\Controller\Site;

use App\Repository\SubscriptionGiftRepository;
use App\Shared\Shop\Exception\SubscriptionGiftException;
use App\Shared\Shop\ShopManager;
use App\Shared\Shop\SubscriptionGiftManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/shop/subscription-gifts', name: 'subscription_gift_')]
#[IsGranted('ROLE_USER')]
class SubscriptionGiftController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SubscriptionGiftManager $gifts): Response
    {
        return $this->page($gifts);
    }

    #[Route('/{id}/claim', name: 'claim', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('subscription-gifts')]
    public function claim(
        int $id,
        Request $request,
        SubscriptionGiftRepository $repository,
        SubscriptionGiftManager $gifts,
    ): Response {
        $gift = $repository->find($id) ?? throw $this->createNotFoundException();

        if ($gift->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $data = $request->request->all();

        try {
            if (($data['terms'] ?? '') !== '1') {
                throw new SubscriptionGiftException('error.shop.subscription_gifts.terms',);
            }

            $gifts->claim($this->getUser(), $gift, (array) ($data['address'] ?? []), (array) ($data['variants'] ?? []),);

            $this->addFlash('success', 'site.shop.subscription_gifts.claimed',);

            return $this->redirectToRoute(
                'site_subscription_gift_index',
            );
        } catch (SubscriptionGiftException $exception) {
            $this->addFlash('error', $exception->getTranslationKey(),);

            return $this->page($gifts, [$id => $data], 422,);
        }
    }

    protected function page(
        SubscriptionGiftManager $gifts,
        array $values = [],
        int $status = 200,
    ): Response {
        return $this->render(
            '@site/pages/shop/subscription_gifts.html.twig',
            [
                'active' => 'shop',
                'packages' => $gifts->forUser($this->getUser()),
                'countries' => ShopManager::COUNTRIES,
                'values' => $values,
            ],
            $status,
        );
    }
}