<?php

namespace App\Controller\Admin;

use App\Entity\ShopOrder;
use App\Entity\ShopProduct;
use App\Entity\User;
use App\Entity\Voucher;
use App\Repository\VoucherRepository;
use App\Shared\Shop\Enum\VoucherKindEnum;
use App\Shared\Shop\Exception\SubscriptionGiftException;
use App\Shared\Shop\SubscriptionGiftManager;
use App\Shared\Shop\VoucherManager;
use App\Repository\BadgeRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ShopOrderRepository;
use App\Repository\ShopProductRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Shared\Exception\BattleGameException;
use App\Shared\Badge\BadgeDesign;
use App\Shared\Helper\SvgSanitizer;
use App\Shared\Shop\CosmeticDesign;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Enum\CosmeticKindEnum;
use App\Shared\Shop\Enum\ShopOrderStatusEnum;
use App\Shared\Shop\Enum\ShopPaymentEnum;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Enum\SubscriptionStatusEnum;
use App\Shared\Shop\ShopManager;
use App\Shared\Shop\StripeClient;
use App\Shared\Shop\SubscriptionManager;
use App\Shared\Shop\WalletManager;
use DateTime;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Request\UploadedFile;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Image\ImageManagerInterface;

#[Route('/shop', name: 'shop_')]
class ShopController extends AbstractAdminController
{
    public const PERIODS = ['1', '7', '30', '90'];
    public const SETTINGS = ['coins_per_match', 'coins_per_win', 'lives_free', 'challenge_play', 'challenge_win', 'challenge_variety', 'referral_referrer', 'referral_referee'];
    public const LISTS = ['daily_rewards', 'season_rewards'];
    public const LEGAL = ['legal_name', 'legal_form', 'legal_address', 'legal_siret', 'legal_vat_number', 'legal_email'];
    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
    private const IMAGE_MAX_SIZE = 3145728;
    private const IMAGE_DIRECTORY = '/uploads/shop';

    public function __construct(
        protected ShopManager $shop,
        protected ShopProductRepository $products,
        protected ShopOrderRepository $orders,
        protected SubscriptionRepository $subscriptionRepository,
        protected SubscriptionManager $subscriptions,
        protected StripeClient $stripe,
        protected SettingManager $settings,
        protected ImageManagerInterface $images,
        #[Autowire(param: 'kernel.public_path')]
        protected string $publicPath,
    ) {
    }

    #[Route('/products', name: 'products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $type = ShopProductTypeEnum::tryFrom((string) $request->query->get('type'));

        return $this->render('@admin/pages/shop/products.html.twig', [
            'products' => array_values(array_filter($this->products->findBy($type === null ? [] : ['type' => $type], ['position' => 'ASC', 'id' => 'ASC']), fn (ShopProduct $product): bool => $type !== null || $product->getType() !== ShopProductTypeEnum::SUBSCRIPTION)),
            'types' => array_values(array_filter(ShopProductTypeEnum::cases(), fn (ShopProductTypeEnum $item): bool => $item !== ShopProductTypeEnum::SUBSCRIPTION)),
            'type' => $type,
            'kpis' => $this->kpis(),
        ]);
    }

    #[Route('/products/new', name: 'product_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-shop')]
    public function newProduct(Request $request, BadgeRepository $badges): Response
    {
        $product = (new ShopProduct())
            ->setType(ShopProductTypeEnum::tryFrom((string) $request->query->get('type')) ?? ShopProductTypeEnum::COSMETIC)
            ->setPosition($this->products->count());

        return $this->handle($request, $product, $badges);
    }

    #[Route('/products/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function editProduct(int $id, Request $request, BadgeRepository $badges): Response
    {
        return $this->handle($request, $this->product($id), $badges);
    }

    #[Route('/products/{id}/toggle', name: 'product_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function toggleProduct(int $id): Response
    {
        $product = $this->product($id);
        $this->products->save($product->setActive(!$product->isActive()), true);
        $this->audit('shop.product', $product->getName());

        return $this->done($this->listRoute($product));
    }

    #[Route('/products/{id}/delete', name: 'product_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function deleteProduct(int $id): Response
    {
        $product = $this->product($id);

        if ($this->orders->count(['product' => $product]) > 0 || $this->get(SubscriptionGiftManager::class)->uses($product)) {
            $this->products->save($product->setActive(false), true);
            $this->addFlash('error', 'admin.shop.products.archived');

            return $this->redirectToRoute($this->listRoute($product));
        }

        $this->deleteTranslations($product);
        $this->products->delete($product, true);
        $this->audit('shop.product_delete', $product->getName());

        return $this->done($this->listRoute($product), [], 'admin.flash.deleted');
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    public function orders(Request $request): Response
    {
        $status = ShopOrderStatusEnum::tryFrom((string) $request->query->get('status'));
        $type = ShopProductTypeEnum::tryFrom((string) $request->query->get('type'));
        $payment = ShopPaymentEnum::tryFrom((string) $request->query->get('payment'));
        $period = in_array($request->query->get('period'), self::PERIODS, true) ? (string) $request->query->get('period') : '';
        $query = mb_strtolower(trim((string) $request->query->get('q')));
        $shipping = $request->query->get('shipping') === '1';
        $since = $period === '' ? null : new DateTime('-' . $period . ' days');
        $orders = array_values(array_filter(
            $this->orders->findBy(array_filter(['status' => $shipping ? ShopOrderStatusEnum::PAID : $status, 'type' => $shipping ? ShopProductTypeEnum::PHYSICAL : $type, 'payment' => $payment]), ['id' => 'DESC']),
            fn (ShopOrder $order): bool => ($since === null || $order->getCreatedAt() >= $since)
                && ($query === '' || str_contains(mb_strtolower(implode(' ', [$order->getReference(), $order->getProductName(), $order->getUser()?->getUsername() ?? '', $order->getUser()?->getEmail() ?? '', $order->getTracking() ?? '', $order->getShipping()['name'] ?? ''])), $query)),
        ));

        return $this->render('@admin/pages/shop/orders.html.twig', [
            'orders' => $orders,
            'statuses' => ShopOrderStatusEnum::cases(),
            'types' => ShopProductTypeEnum::cases(),
            'payments' => ShopPaymentEnum::cases(),
            'periods' => self::PERIODS,
            'filters' => ['q' => (string) $request->query->get('q'), 'status' => $status, 'type' => $type, 'payment' => $payment, 'period' => $period, 'shipping' => $shipping],
            'filtered' => $query !== '' || $status !== null || $type !== null || $payment !== null || $period !== '' || $shipping,
            'kpis' => $this->kpis(),
        ]);
    }

    #[Route('/invoices/{number}', name: 'invoice', methods: ['GET'], requirements: ['number' => '(FAC|AV)-\d{4}-\d{5}'])]
    public function invoice(string $number, InvoiceRepository $invoices): Response
    {
        $this->allow('shop.orders');
        $invoice = $invoices->findOneBy(['number' => $number]) ?? throw $this->createNotFoundException();

        return $this->render('shared/invoice.html.twig', ['invoice' => $invoice, 'back' => $invoice->getOrder() ? $this->generateUrl('admin_shop_order', ['id' => $invoice->getOrder()->getId()]) : $this->generateUrl('admin_shop_orders')]);
    }

    #[Route('/orders/{id}', name: 'order', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function order(int $id, InvoiceRepository $invoices): Response
    {
        $order = $this->findOrder($id);

        return $this->render('@admin/pages/shop/order.html.twig', [
            'order' => $order,
            'invoices' => $invoices->findBy(['order' => $order], ['id' => 'ASC']),
            'stripe' => $this->stripe->testMode() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com',
        ]);
    }

    #[Route('/orders/{id}/{action}', name: 'order_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'ship|cancel|refund|deliver'])]
    #[Csrf('admin-shop')]
    public function orderAction(int $id, string $action, Request $request): Response
    {
        $this->allow('shop.orders');
        $order = $this->findOrder($id);

        try {
            match ($action) {
                'ship' => $this->shop->ship($order, mb_substr(trim((string) $request->request->get('tracking')), 0, 120)),
                'cancel' => $this->shop->cancel($order),
                'refund' => $this->shop->refund($order),
                'deliver' => $this->shop->complete($order),
            };
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->translate());

            return $this->redirectToRoute('admin_shop_order', ['id' => $id]);
        }

        $note = trim((string) $request->request->get('note'));

        if ($note !== '') {
            $this->orders->save($order->setNote(mb_substr($note, 0, 500)), true);
        }

        $this->audit('shop.order_' . $action, $order->getReference());

        return $this->done('admin_shop_order', ['id' => $id]);
    }

    #[Route('/vouchers', name: 'vouchers', methods: ['GET'])]
    public function vouchers(Request $request, VoucherRepository $vouchers): Response
    {
        $kind = VoucherKindEnum::tryFrom((string) $request->query->get('kind'));
        $query = mb_strtoupper(trim((string) $request->query->get('q')));

        return $this->render('@admin/pages/shop/vouchers.html.twig', [
            'vouchers' => array_values(array_filter($vouchers->findBy($kind === null ? [] : ['kind' => $kind], ['id' => 'DESC']), fn (Voucher $voucher): bool => $query === '' || str_contains($voucher->getCode() . ' ' . mb_strtoupper((string) $voucher->getNote()), $query))),
            'kinds' => VoucherKindEnum::cases(),
            'kind' => $kind,
            'q' => (string) $request->query->get('q'),
        ]);
    }

    #[Route('/vouchers', name: 'voucher_new', methods: ['POST'])]
    #[Csrf('admin-shop')]
    public function newVoucher(Request $request, VoucherRepository $vouchers, VoucherManager $manager): Response
    {
        $data = $request->request->all();
        $kind = VoucherKindEnum::tryFrom((string) ($data['kind'] ?? '')) ?? VoucherKindEnum::PROMO;
        $code = $manager->normalize((string) ($data['code'] ?? ''));
        $code = $code === '' ? $manager->generate($kind === VoucherKindEnum::GIFT ? 'GIFT' : '') : $code;
        $value = trim((string) ($data['value'] ?? ''));
        $count = max(1, min(200, (int) ($data['count'] ?? 1)));

        if (!preg_match('/^[A-Z0-9-]{3,30}$/', $code) || ($count === 1 && $vouchers->findOneBy(['code' => $code]) !== null) || $value === '') {
            $this->addFlash('error', 'admin.shop.vouchers.invalid');

            return $this->redirectToRoute('admin_shop_vouchers');
        }

        for ($i = 0; $i < $count; $i++) {
            $voucher = (new Voucher())
                ->setCode($count === 1 ? $code : $manager->generate($kind === VoucherKindEnum::GIFT ? 'GIFT' : ''))
                ->setKind($kind)
                ->setMaxUses(trim((string) ($data['max_uses'] ?? '')) === '' ? null : max(1, (int) $data['max_uses']))
                ->setEndsAt(DateTime::createFromFormat('Y-m-d', (string) ($data['ends_at'] ?? '')) ? DateTime::createFromFormat('Y-m-d H:i:s', $data['ends_at'] . ' 23:59:59') : null)
                ->setNote(mb_substr(trim((string) ($data['note'] ?? '')), 0, 160) ?: null);

            if ($kind === VoucherKindEnum::GIFT) {
                $voucher->setCoins(max(1, (int) $value));
            } elseif (str_ends_with($value, '%')) {
                $voucher->setPercent(max(1, min(90, (int) $value)));
            } else {
                $voucher->setAmountCents(max(1, (int) round((float) str_replace(',', '.', $value) * 100)));
            }

            $vouchers->save($voucher);
        }

        $vouchers->getOrm()->flush();
        $this->audit('shop.voucher', $count . ' × ' . $kind->value);

        return $this->done('admin_shop_vouchers');
    }

    #[Route('/vouchers/{id}/toggle', name: 'voucher_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function toggleVoucher(int $id, VoucherRepository $vouchers): Response
    {
        $voucher = $vouchers->find($id) ?? throw $this->createNotFoundException();
        $vouchers->save($voucher->setActive(!$voucher->isActive()), true);
        $this->audit('shop.voucher', $voucher->getCode());

        return $this->done('admin_shop_vouchers');
    }

    #[Route('/vouchers/{id}/delete', name: 'voucher_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function deleteVoucher(int $id, VoucherRepository $vouchers): Response
    {
        $voucher = $vouchers->find($id) ?? throw $this->createNotFoundException();

        if ($voucher->getUses() > 0) {
            $vouchers->save($voucher->setActive(false), true);
            $this->addFlash('error', 'admin.shop.vouchers.archived');

            return $this->redirectToRoute('admin_shop_vouchers');
        }

        $vouchers->delete($voucher, true);
        $this->audit('shop.voucher_delete', $voucher->getCode());

        return $this->done('admin_shop_vouchers', [], 'admin.flash.deleted');
    }

    #[Route('/subscriptions', name: 'subscriptions', methods: ['GET'])]
    public function subscriptions(Request $request): Response
    {
        $status = SubscriptionStatusEnum::tryFrom((string) $request->query->get('status'));

        return $this->render('@admin/pages/shop/subscriptions.html.twig', [
            'subscriptions' => $this->subscriptionRepository->findBy($status === null ? [] : ['status' => $status], ['id' => 'DESC']),
            'statuses' => SubscriptionStatusEnum::cases(),
            'status' => $status,
            'offers' => array_map(fn (ShopProduct $offer): array => ['product' => $offer, 'subscribers' => $this->subscriptions->subscribers($offer)], $this->products->findBy(['type' => ShopProductTypeEnum::SUBSCRIPTION], ['position' => 'ASC', 'id' => 'ASC'])),
            'kpis' => $this->kpis(),
        ]);
    }

    #[Route('/subscriptions/grant', name: 'subscription_grant', methods: ['POST'])]
    #[Csrf('admin-shop')]
    public function grant(Request $request, UserRepository $users): Response
    {
        $this->allow('shop.manage');
        $user = $users->findOneBy(['username' => trim((string) $request->request->get('username'))]);
        $offer = $this->products->findOneBy(['id' => (int) $request->request->get('offer'), 'type' => ShopProductTypeEnum::SUBSCRIPTION]);
        $days = max(1, min(366, (int) $request->request->get('days')));

        if ($user === null || $user->isGuest() || $offer === null) {
            $this->addFlash('error', 'admin.shop.subscriptions.grant_invalid');

            return $this->redirectToRoute('admin_shop_subscriptions');
        }

        $this->subscriptions->grant($user, $offer, new DateTime('+' . $days . ' days'));
        $this->audit('shop.subscription_grant', $user->getUsername() . ' · ' . $offer->getName() . ' · ' . $days . ' j');

        return $this->done('admin_shop_subscriptions');
    }

    #[Route('/subscriptions/{id}/cancel', name: 'subscription_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-shop')]
    public function cancelSubscription(int $id): Response
    {
        $this->allow('shop.manage');
        $subscription = $this->subscriptionRepository->find($id) ?? throw $this->createNotFoundException();

        try {
            $this->shop->cancelSubscription($subscription);
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->translate());

            return $this->redirectToRoute('admin_shop_subscriptions');
        }

        $this->audit('shop.subscription_cancel', $subscription->getUser()->getUsername());

        return $this->done('admin_shop_subscriptions');
    }

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    #[Csrf('admin-shop')]
    public function settings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            $this->settings->set('shop_enabled', $request->request->get('shop_enabled') !== null);
            $this->settings->set('vat_exempt', $request->request->get('vat_exempt') !== null);
            $this->settings->set('vat_rate', max(0, min(100, (float) str_replace(',', '.', (string) $request->request->get('vat_rate')))));

            foreach (self::LISTS as $key) {
                $this->settings->set($key, implode(',', array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $request->request->get($key))), fn (int $value): bool => $value > 0)), 0, 30)));
            }

            foreach (self::LEGAL as $key) {
                $this->settings->set($key, mb_substr(trim((string) $request->request->get($key)), 0, $key === 'legal_address' ? 300 : 120));
            }

            foreach (self::SETTINGS as $key) {
                $this->settings->set($key, max(0, min(100000, (int) $request->request->get($key))));
            }

            $this->audit('shop.settings');

            return $this->done('admin_shop_settings');
        }

        return $this->render('@admin/pages/shop/settings.html.twig', [
            'legal' => array_combine(self::LEGAL, array_map(fn (string $key): string => (string) $this->settings->get($key), self::LEGAL)) + ['vat_rate' => (float) $this->settings->get('vat_rate'), 'vat_exempt' => (bool) $this->settings->get('vat_exempt')],
            'lists' => array_combine(self::LISTS, array_map(fn (string $key): string => str_replace(',', ', ', (string) $this->settings->get($key)), self::LISTS)),
            'values' => ['shop_enabled' => $this->settings->get('shop_enabled'), ...array_combine(self::SETTINGS, array_map(fn (string $key): int => (int) $this->settings->get($key), self::SETTINGS))],
            'stripe' => ['configured' => $this->stripe->configured(), 'test' => $this->stripe->testMode(), 'webhook' => $this->stripe->webhookConfigured()],
            'webhook' => $this->generateUrl('site_shop_webhook', [], true),
        ]);
    }

    #[Route('/players', name: 'players', methods: ['GET'])]
    public function players(Request $request, UserRepository $users, WalletManager $wallets): Response
    {
        $query = trim((string) $request->query->get('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(fn (User $user): array => [
            'username' => $user->getUsername(),
            'initials' => $user->getInitials(),
            'avatar' => $user->getAvatar(),
            'coins' => $wallets->balance($user),
            'plan' => $this->subscriptions->current($user)?->getName(),
        ], $users->findPlayers($query, 8)));
    }

    #[Route('/coins', name: 'coins', methods: ['POST'])]
    #[Csrf('admin-shop')]
    public function coins(Request $request, UserRepository $users, WalletManager $wallets): Response
    {
        $user = $users->findOneBy(['username' => trim((string) $request->request->get('username'))]);
        $remove = $request->request->get('operation') === 'remove';
        $amount = max(0, min(1000000, abs((int) $request->request->get('amount'))));
        $label = mb_substr(trim((string) $request->request->get('label')), 0, 160) ?: 'Ajustement';

        if ($user === null || $user->isGuest() || $amount === 0) {
            $this->addFlash('error', 'admin.shop.settings.coins_invalid');

            return $this->redirectToRoute('admin_shop_settings');
        }

        if ($remove && $wallets->balance($user) < $amount) {
            $this->addFlash('error', 'admin.shop.settings.not_enough');

            return $this->redirectToRoute('admin_shop_settings');
        }

        $wallets->credit($user, $remove ? -$amount : $amount, 'admin', $label);
        $this->audit('shop.coins', $user->getUsername() . ' · ' . ($remove ? '-' : '+') . $amount);

        return $this->done('admin_shop_settings', [], $remove ? 'admin.shop.settings.removed' : 'admin.shop.settings.added');
    }

    protected function handle(Request $request, ShopProduct $product, BadgeRepository $badges): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $type = ShopProductTypeEnum::tryFrom((string) ($data['type'] ?? '')) ?? $product->getType();
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);
            $slug = $this->slug((string) ($data['slug'] ?? '') ?: $name);
            $cents = $this->cents((string) ($data['price_euros'] ?? ''));
            $coins = trim((string) ($data['price_coins'] ?? '')) === '' ? null : max(1, (int) $data['price_coins']);
            $other = $slug === '' ? null : $this->products->findOneBy(['slug' => $slug]);

            if ($name === '') {
                $errors['name'] = 'admin.shop.products.errors.name';
            }

            if ($slug === '' || ($other !== null && $other->getId() !== $product->getId())) {
                $errors['slug'] = 'admin.shop.products.errors.slug';
            }

            if ($cents === null && ($coins === null || !$type->allowsCoins())) {
                $errors['price'] = $type->allowsCoins() ? 'admin.shop.products.errors.price' : 'admin.shop.products.errors.price_card';
            }

            if ($product->getId() !== null && $type !== $product->getType() && $this->get(SubscriptionGiftManager::class)->uses($product)) {
                $errors['name'] = 'admin.shop.products.errors.included_type';
            }

            $payload = $this->payload($type, $data, $errors, $badges);
            $file = $request->files->get('image');

            if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE && ($file->isValid() === false || !in_array($file->getMimeType(), self::IMAGE_TYPES, true) || $file->getSize() > self::IMAGE_MAX_SIZE)) {
                $errors['image'] = 'admin.shop.products.errors.image';
            }

            if ($errors === []) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $directory = $this->publicPath . self::IMAGE_DIRECTORY;

                    if (!is_dir($directory)) {
                        mkdir($directory, 0775, true);
                    }

                    $path = self::IMAGE_DIRECTORY . '/' . $slug . '-' . bin2hex(random_bytes(4)) . '.webp';
                    $this->images->open($file->getPath())->autoOrient()->cover(640, 400, 'center', true)->save($this->publicPath . $path, 'webp');
                    $this->removeImage($product);
                    $product->setImage($path);
                } elseif (isset($data['remove_image'])) {
                    $this->removeImage($product);
                    $product->setImage(null);
                }

                $stock = trim((string) ($data['stock'] ?? ''));
                $this->products->save($product
                    ->setName($name)
                    ->setSlug($slug)
                    ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 2000))
                    ->setType($type)
                    ->setPriceCents($cents)
                    ->setPriceCoins($type->allowsCoins() ? $coins : null)
                    ->setStock($stock === '' ? null : max(0, (int) $stock))
                    ->setPosition(max(0, (int) ($data['position'] ?? 0)))
                    ->setPayload($payload)
                    ->setActive(isset($data['active'])), true);
                $this->saveTranslations($product, $request);
                $this->audit('shop.product', $name);

                return $this->done($type === ShopProductTypeEnum::SUBSCRIPTION ? 'admin_shop_subscriptions' : 'admin_shop_products');
            }

            $product->setType($type)->setName($name)->setSlug($slug)->setDescription((string) ($data['description'] ?? ''))->setPriceCents($cents)->setPriceCoins($coins)->setPayload($payload);
        }

        return $this->render('@admin/pages/shop/product.html.twig', [
            'product' => $product,
            'errors' => $errors,
            'types' => ShopProductTypeEnum::cases(),
            'kinds' => CosmeticKindEnum::cases(),
            'design' => [
                'paints' => CosmeticDesign::PAINTS,
                'animations' => CosmeticDesign::ANIMATIONS,
                'patterns' => CosmeticDesign::PATTERNS,
                'effects' => CosmeticDesign::EFFECTS,
                'shapes' => BadgeDesign::SHAPES,
                'rarities' => BadgeDesign::RARITIES,
                'icons' => BadgeDesign::ICONS,
            ],
            'icons' => ShopProduct::PLAN_ICONS,
            'badges' => $badges->findAll(['name' => 'ASC']),
            'card' => $this->stripe->configured(),
            'pass_tracks' => $this->passTracks(),
            'included_candidates' => $this->get(SubscriptionGiftManager::class)->candidates(),
        ], $errors === [] ? 200 : 422);
    }

    protected function payload(ShopProductTypeEnum $type, array $data, array &$errors, BadgeRepository $badges): array
    {
        $payload = [];

        switch ($type) {
            case ShopProductTypeEnum::COINS:
                $payload['coins'] = max(0, (int) ($data['coins'] ?? 0));
                $payload['coins'] > 0 || $errors['coins'] = 'admin.shop.products.errors.amount';
                break;
            case ShopProductTypeEnum::LIVES:
                $payload['lives'] = max(0, (int) ($data['lives'] ?? 0));
                $payload['lives'] > 0 || $errors['lives'] = 'admin.shop.products.errors.amount';
                break;
            case ShopProductTypeEnum::SUBSCRIPTION:
                $payload['lives'] = isset($data['unlimited']) ? 0 : max(1, (int) ($data['plan_lives'] ?? 0));
                $payload['color'] = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($data['color'] ?? '')) ? strtoupper((string) $data['color']) : ShopProduct::PLAN_COLOR;
                $payload['icon'] = preg_match('/^[a-z0-9-]{1,60}$/', (string) ($data['icon'] ?? '')) ? (string) $data['icon'] : 'crown';
                $payload['icon_svg'] = SvgSanitizer::icon((string) ($data['icon_svg'] ?? ''));
                $payload['label'] = mb_substr(trim((string) ($data['label'] ?? '')), 0, 30);
                $payload['early_access'] = isset($data['early_access']);
                $payload['pass_tracks'] = array_values(array_intersect(array_column($this->passTracks(), 'key'), array_map('strval', (array) ($data['pass_tracks'] ?? []))));

                try {
                    $payload['included_products'] = $this->get(SubscriptionGiftManager::class)->normalize((array) ($data['included_products'] ?? []));
                } catch (SubscriptionGiftException) {
                    $payload['included_products'] = [];
                    $errors['included_products'] = 'admin.shop.products.errors.included_products';
                }

                $payload['perks'] = array_values(array_filter(array_map(fn (string $line): string => mb_substr(trim($line), 0, 120), explode("\n", (string) ($data['perks'] ?? ''))), fn (string $line): bool => $line !== ''));
                break;
            case ShopProductTypeEnum::BADGE:
                $badge = $badges->find((int) ($data['badge'] ?? 0));
                $payload['badge'] = $badge?->getId();
                $badge !== null || $errors['badge'] = 'admin.shop.products.errors.badge';
                break;
            case ShopProductTypeEnum::COSMETIC:
                $kind = CosmeticKindEnum::tryFrom((string) ($data['kind'] ?? '')) ?? CosmeticKindEnum::FRAME;
                $value = trim((string) ($data['value_' . $kind->value] ?? ''));
                $payload['kind'] = $kind->value;
                $payload['value'] = match ($kind) {
                    CosmeticKindEnum::COLOR => preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : null,
                    CosmeticKindEnum::TITLE => mb_substr($value, 0, 30) ?: null,
                    default => 'custom',
                };

                if (in_array($kind, [CosmeticKindEnum::FRAME, CosmeticKindEnum::BANNER, CosmeticKindEnum::VICTORY], true)) {
                    $design = (array) ($data['design'][$kind->value] ?? []);
                    $payload['design'] = match ($kind) {
                        CosmeticKindEnum::FRAME => CosmeticDesign::frame($design),
                        CosmeticKindEnum::BANNER => CosmeticDesign::banner($design),
                        CosmeticKindEnum::VICTORY => CosmeticDesign::victory($design),
                    };
                }
                $payload['value'] !== null || $errors['value'] = 'admin.shop.products.errors.value';
                break;
            case ShopProductTypeEnum::GIFT:
                $payload['coins'] = max(0, (int) ($data['gift_coins'] ?? 0));
                $payload['coins'] > 0 || $errors['coins'] = 'admin.shop.products.errors.amount';
                break;
            case ShopProductTypeEnum::PHYSICAL:
                $payload['sizes'] = mb_substr(trim((string) ($data['sizes'] ?? '')), 0, 120);
                break;
        }

        return $payload;
    }

    protected function kpis(): array
    {
        $connection = $this->orders->getOrm()->getConnection();

        return [
            'revenue' => (int) $connection->fetchOne("SELECT COALESCE(SUM(amount_cents), 0) FROM shop_order WHERE payment = 'card' AND status IN ('paid', 'shipped') AND paid_at >= :start", ['start' => (new DateTime('first day of this month 00:00'))->format('Y-m-d H:i:s')]),
            'orders' => (int) $connection->fetchOne("SELECT COUNT(*) FROM shop_order WHERE status IN ('paid', 'shipped') AND paid_at >= :start", ['start' => (new DateTime('first day of this month 00:00'))->format('Y-m-d H:i:s')]),
            'shipping' => (int) $connection->fetchOne("SELECT COUNT(*) FROM shop_order WHERE status = 'paid' AND type = 'physical'"),
            'subscribers' => count($this->subscriptions->active()),
            'coins' => (int) $connection->fetchOne('SELECT COALESCE(SUM(coins), 0) FROM wallet'),
        ];
    }

    protected function slug(string $value): string
    {
        $value = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value));

        return mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-'), 0, 100);
    }

    protected function cents(string $value): ?int
    {
        $value = str_replace([' ', ','], ['', '.'], trim($value));

        return is_numeric($value) && (float) $value > 0 ? (int) round((float) $value * 100) : null;
    }

    protected function removeImage(ShopProduct $product): void
    {
        $image = $product->getImage();

        if ($image !== null && str_starts_with($image, self::IMAGE_DIRECTORY . '/') && is_file($this->publicPath . $image)) {
            unlink($this->publicPath . $image);
        }
    }

    protected function listRoute(ShopProduct $product): string
    {
        return $product->getType() === ShopProductTypeEnum::SUBSCRIPTION ? 'admin_shop_subscriptions' : 'admin_shop_products';
    }

    protected function product(int $id): ShopProduct
    {
        return $this->products->find($id) ?? throw $this->createNotFoundException();
    }

    protected function findOrder(int $id): ShopOrder
    {
        return $this->orders->find($id) ?? throw $this->createNotFoundException();
    }

    protected function passTracks(): array
    {
        return array_values(array_filter((array) $this->settings->get('pass_tracks'), fn (mixed $track): bool => is_array($track) && isset($track['key'], $track['name'])));
    }
}
