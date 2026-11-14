<?php

namespace App\Controller\Site;

use App\Shared\Shop\ShopManager;
use App\Shared\Shop\StripeClient;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

class ShopWebhookController extends AbstractController
{
    #[Route('/shop/webhook/stripe', name: 'shop_webhook', methods: ['POST'])]
    public function __invoke(Request $request, StripeClient $stripe, ShopManager $shop): Response
    {
        $event = $stripe->verify($request->getContent(), (string) $request->headers->get('Stripe-Signature'));

        if ($event === null) {
            return new Response('invalid', 400);
        }

        $shop->webhook($event);

        return new Response('ok');
    }
}
