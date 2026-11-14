<?php

namespace App\Shared\Shop;

use App\Entity\Invoice;
use App\Entity\ShopOrder;
use App\Repository\InvoiceRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Enum\InvoiceKindEnum;
use App\Shared\Shop\Enum\ShopPaymentEnum;
use DateTime;

class InvoiceManager
{
    public const SETTINGS = ['legal_name', 'legal_form', 'legal_address', 'legal_siret', 'legal_vat_number', 'legal_email', 'vat_rate', 'vat_exempt'];

    public function __construct(
        protected InvoiceRepository $invoices,
        protected SettingManager $settings,
    ) {
    }

    public function forOrder(ShopOrder $order, InvoiceKindEnum $kind = InvoiceKindEnum::INVOICE): ?Invoice
    {
        return $this->invoices->findOneBy(['order' => $order, 'kind' => $kind]);
    }

    public function issue(ShopOrder $order): ?Invoice
    {
        if ($order->getPayment() !== ShopPaymentEnum::CARD || (int) $order->getAmountCents() <= 0) {
            return null;
        }

        return $this->forOrder($order) ?? $this->create($order, InvoiceKindEnum::INVOICE, null);
    }

    public function credit(ShopOrder $order): ?Invoice
    {
        $invoice = $this->forOrder($order);

        if ($invoice === null) {
            return null;
        }

        return $this->forOrder($order, InvoiceKindEnum::CREDIT) ?? $this->create($order, InvoiceKindEnum::CREDIT, $invoice);
    }

    public function exempt(): bool
    {
        return (bool) $this->settings->get('vat_exempt');
    }

    protected function create(ShopOrder $order, InvoiceKindEnum $kind, ?Invoice $source): Invoice
    {
        $connection = $this->invoices->getOrm()->getConnection();
        $connection->fetchOne("SELECT GET_LOCK('battlegame_invoice', 10)");

        try {
            $year = (int) date('Y');
            $prefix = $kind->prefix() . '-' . $year . '-';
            $last = (string) $connection->fetchOne('SELECT number FROM invoice WHERE number LIKE :prefix ORDER BY number DESC LIMIT 1', ['prefix' => $prefix . '%']);
            $number = $prefix . str_pad((string) ((int) substr($last, strlen($prefix)) + 1), 5, '0', STR_PAD_LEFT);
            $total = $source?->getTotalCents() ?? (int) $order->getAmountCents();
            $rate = $source?->getVatRate() ?? ($this->exempt() ? 0.0 : (float) $this->settings->get('vat_rate'));
            $vat = $source?->getVatCents() ?? ($rate > 0 ? $total - (int) round($total / (1 + $rate / 100)) : 0);
            $label = $order->getProductName() . ($order->getVariant() ? ' (' . $order->getVariant() . ')' : '');
            $label .= $order->getVoucherCode() ? ' — code ' . $order->getVoucherCode() . ' (-' . number_format((int) $order->getDiscountCents() / 100, 2, ',', ' ') . ' €)' : '';
            $invoice = (new Invoice())
                ->setNumber($number)
                ->setKind($kind)
                ->setOrder($order)
                ->setUser($order->getUser())
                ->setReference($source?->getNumber() ?? $order->getReference())
                ->setSeller($source?->getSeller() ?? $this->seller())
                ->setBuyer($source?->getBuyer() ?? $this->buyer($order))
                ->setLines([[
                    'label' => $label,
                    'type' => $order->getType()->value,
                    'quantity' => 1,
                    'net' => $total - $vat,
                    'vat' => $vat,
                    'total' => $total,
                ]])
                ->setTotalCents($total)
                ->setVatCents($vat)
                ->setVatRate($rate)
                ->setIssuedAt(new DateTime());
            $this->invoices->save($invoice, true);
        } finally {
            $connection->fetchOne("SELECT RELEASE_LOCK('battlegame_invoice')");
        }

        return $invoice;
    }

    protected function seller(): array
    {
        return [
            'name' => (string) $this->settings->get('legal_name'),
            'form' => (string) $this->settings->get('legal_form'),
            'address' => (string) $this->settings->get('legal_address'),
            'siret' => (string) $this->settings->get('legal_siret'),
            'vat_number' => (string) $this->settings->get('legal_vat_number'),
            'email' => (string) $this->settings->get('legal_email'),
            'exempt' => $this->exempt(),
        ];
    }

    protected function buyer(ShopOrder $order): array
    {
        $user = $order->getUser();
        $shipping = $order->getShipping();

        return array_filter([
            'name' => $shipping['name'] ?? $user?->getUsername(),
            'username' => $user?->getUsername(),
            'email' => $user?->getEmail(),
            'address' => trim(implode("\n", array_filter([$shipping['line1'] ?? null, $shipping['line2'] ?? null, trim(($shipping['postal_code'] ?? '') . ' ' . ($shipping['city'] ?? '')), $shipping['country'] ?? null]))),
        ]);
    }
}
