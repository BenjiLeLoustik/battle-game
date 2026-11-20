<?php

namespace App\Controller\Site;

use App\Entity\Invoice;
use App\Entity\ShopProduct;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\ShopOrderRepository;
use App\Repository\ShopProductRepository;
use App\Repository\UserItemRepository;
use App\Shared\Exception\BattleGameException;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Enum\ShopOrderStatusEnum;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\ShopManager;
use App\Shared\Shop\StripeClient;
use App\Shared\Shop\SubscriptionManager;
use App\Shared\Shop\VoucherManager;
use App\Shared\Shop\WalletManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/shop', name: 'shop_')]
class ShopController extends AbstractController
{
    public const TABS = ['all', 'subscription', 'coins', 'lives', 'badge', 'cosmetic', 'gift', 'physical'];

    public function __construct(
        protected ShopManager $shop,
        protected ShopProductRepository $products,
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected SubscriptionManager $subscriptions,
        protected StripeClient $stripe,
        protected SettingManager $settings,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->open();
        $tab = in_array($request->query->get('tab'), self::TABS, true) ? (string) $request->query->get('tab') : 'all';
        $user = $this->member();
        $products = $this->shop->catalog($tab === 'all' ? null : ShopProductTypeEnum::from($tab));
        $owned = [];

        foreach ($user !== null ? $products : [] as $product) {
            $owned[$product->getId()] = $this->shop->owns($user, $product);
        }

        $counts = array_count_values(array_map(fn (ShopProduct $product): string => $product->getType()->value, $this->shop->catalog()));

        return $this->render('@site/pages/shop/index.html.twig', [
            'active' => 'shop',
            'tab' => $tab,
            'tabs' => array_values(array_filter(self::TABS, fn (string $item): bool => $item === 'all' || isset($counts[$item]))),
            'counts' => ['all' => array_sum($counts)] + $counts,
            'plans' => array_values(array_filter($products, fn (ShopProduct $product): bool => $product->getType() === ShopProductTypeEnum::SUBSCRIPTION)),
            'products' => array_values(array_filter($products, fn (ShopProduct $product): bool => $product->getType() !== ShopProductTypeEnum::SUBSCRIPTION)),
            'owned' => $owned,
            'card' => $this->stripe->configured(),
            'subscription' => $user !== null ? $this->subscriptions->current($user) : null,
            'rewards' => ['match' => (int) $this->settings->get('coins_per_match'), 'win' => (int) $this->settings->get('coins_per_win')],
        ]);
    }

    #[Route('/{slug}/coins', name: 'buy', methods: ['POST'], requirements: ['slug' => '[a-z0-9-]+'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('shop')]
    public function buy(string $slug): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        try {
            $order = $this->shop->buyWithCoins($user, $this->product($slug));
            $this->addFlash('success', 'site.shop.flash.bought');

            return $this->redirectToRoute('site_shop_success', ['order' => $order->getReference()]);
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->getTranslationKey());

            return $this->redirectToRoute('site_shop_index');
        }
    }

    #[Route('/{slug}/card', name: 'checkout', methods: ['POST'], requirements: ['slug' => '[a-z0-9-]+'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('shop')]
    public function checkout(string $slug, Request $request): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        if ($request->request->get('terms') !== '1') {
            $this->addFlash('error', 'site.shop.flash.terms');

            return $this->redirectToRoute('site_shop_index');
        }

        try {
            return $this->redirect($this->shop->checkout($user, $this->product($slug), $this->generateUrl('site_shop_success', [], true), $this->generateUrl('site_shop_cancel', [], true), $request->request->get('variant') !== null ? (string) $request->request->get('variant') : null, (string) $request->request->get('code')));
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->getTranslationKey());

            return $this->redirectToRoute('site_shop_index');
        }
    }

    #[Route('/success', name: 'success', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function success(Request $request, ShopOrderRepository $orders): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $session = (string) $request->query->get('session');
        $order = null;

        try {
            $order = $session !== '' ? $this->shop->confirm($user, $session) : $orders->findOneBy(['reference' => (string) $request->query->get('order'), 'user' => $user]);
        } catch (BattleGameException) {
            $order = $orders->findOneBy(['stripeSession' => $session, 'user' => $user]);
        }

        if ($order === null) {
            return $this->redirectToRoute('site_shop_orders');
        }

        return $this->render('@site/pages/shop/success.html.twig', [
            'active' => 'shop',
            'order' => $order,
            'paid' => $order->getStatus() !== ShopOrderStatusEnum::PENDING,
            'gift' => $this->get(VoucherManager::class)->giftFor($order),
        ]);
    }

    #[Route('/cancel', name: 'cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        $this->addFlash('error', 'site.shop.flash.cancelled');

        return $this->redirectToRoute('site_shop_index');
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function orders(ShopOrderRepository $orders, InvoiceRepository $invoices): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        return $this->render('@site/pages/shop/orders.html.twig', [
            'active' => 'shop',
            'orders' => $orders->findBy(['user' => $user], ['id' => 'DESC']),
            'invoices' => array_reduce($invoices->findBy(['user' => $user], ['id' => 'ASC']), function (array $map, Invoice $invoice): array {
                $map[$invoice->getOrder()?->getId() ?? 0][$invoice->getKind()->value] = $invoice->getNumber();

                return $map;
            }, []),
            'history' => $this->wallets->history($user, 30),
            'gifts' => array_reduce($orders->findBy(['user' => $user, 'type' => ShopProductTypeEnum::GIFT]), function (array $map, $order): array {
                $gift = $this->get(VoucherManager::class)->giftFor($order);

                if ($gift !== null) {
                    $map[$order->getId()] = $gift;
                }

                return $map;
            }, []),
        ]);
    }

    #[Route('/invoices/{number}', name: 'invoice', methods: ['GET'], requirements: ['number' => '(FAC|AV)-\d{4}-\d{5}'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function invoice(string $number, InvoiceRepository $invoices): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $invoice = $invoices->findOneBy(['number' => $number, 'user' => $user]) ?? throw $this->createNotFoundException();

        return $this->render('shared/invoice.html.twig', ['invoice' => $invoice, 'back' => $this->generateUrl('site_shop_orders')]);
    }

    #[Route('/redeem', name: 'redeem', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('shop')]
    public function redeem(Request $request, VoucherManager $vouchers): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $redeemed = null;

        if ($request->getMethod() === 'POST') {
            try {
                $redeemed = $vouchers->redeem($user, (string) $request->request->get('code'));
            } catch (BattleGameException $exception) {
                $this->addFlash('error', $exception->getTranslationKey());

                return $this->redirectToRoute('site_shop_redeem');
            }
        }

        return $this->render('@site/pages/shop/redeem.html.twig', ['active' => 'shop', 'redeemed' => $redeemed]);
    }

    #[Route('/inventory', name: 'inventory', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function inventory(): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        return $this->render('@site/pages/shop/inventory.html.twig', [
            'active' => 'shop',
            'items' => $this->shop->inventory($user),
            'subscription' => $this->subscriptions->current($user),
        ]);
    }

    #[Route('/inventory/{id}/equip', name: 'equip', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('shop')]
    public function equip(int $id, UserItemRepository $items): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $this->shop->equip($user, $items->find($id) ?? throw $this->createNotFoundException());
        $this->addFlash('success', 'site.shop.flash.equipped');

        return $this->redirectToRoute('site_shop_inventory');
    }

    #[Route('/subscription/cancel', name: 'unsubscribe', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('shop')]
    public function unsubscribe(): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $subscription = $this->subscriptions->current($user) ?? throw $this->createNotFoundException();

        try {
            $this->shop->cancelSubscription($subscription);
            $this->addFlash('success', 'site.shop.flash.unsubscribed');
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->getTranslationKey());
        }

        return $this->redirectToRoute('site_shop_inventory');
    }

    protected function product(string $slug): ShopProduct
    {
        return $this->products->findOneBy(['slug' => $slug, 'active' => true]) ?? throw $this->createNotFoundException();
    }

    protected function member(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User && !$user->isGuest() ? $user : null;
    }

    protected function open(): void
    {
        if (!$this->settings->get('shop_enabled') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }
    }
}
