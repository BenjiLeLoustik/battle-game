<?php

namespace App\Shared\Shop;

use App\Shared\Shop\Exception\PaymentException;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\HttpClient\HttpClientManagerInterface;
use Throwable;

class StripeClient
{
    private const API = 'https://api.stripe.com/v1/';
    private const TOLERANCE = 300;

    public function __construct(
        protected HttpClientManagerInterface $http,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        protected string $secret = '',
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        protected string $webhookSecret = '',
    ) {
    }

    public function configured(): bool
    {
        return str_starts_with($this->secret, 'sk_');
    }

    public function testMode(): bool
    {
        return str_starts_with($this->secret, 'sk_test_');
    }

    public function webhookConfigured(): bool
    {
        return str_starts_with($this->webhookSecret, 'whsec_');
    }

    public function call(string $method, string $path, array $data = []): array
    {
        if (!$this->configured()) {
            throw new PaymentException();
        }

        try {
            $options = ['auth_bearer' => $this->secret, 'timeout' => 20];

            if ($data !== []) {
                $options['body'] = http_build_query($data);
                $options['headers'] = ['Content-Type' => 'application/x-www-form-urlencoded'];
            }

            return $this->http->request($method, self::API . $path, $options)->toArray();
        } catch (Throwable $exception) {
            throw new PaymentException([], $exception);
        }
    }

    public function verify(string $payload, string $header): ?array
    {
        if (!$this->webhookConfigured()) {
            return null;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);
        $valid = array_filter($parts['v1'] ?? [], fn (string $signature): bool => hash_equals($expected, $signature));

        if ($valid === [] || abs(time() - $timestamp) > self::TOLERANCE) {
            return null;
        }

        $event = json_decode($payload, true);

        return is_array($event) ? $event : null;
    }
}
