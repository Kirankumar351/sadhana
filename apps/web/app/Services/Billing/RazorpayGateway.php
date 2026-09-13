<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Billing\Contracts\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay.
 *
 * UPI-first, because that is how this audience actually pays: card penetration in the
 * target segment is low, and UPI has no per-transaction floor that would make a Rs 199
 * product uneconomic.
 *
 * WE NEVER SEE A CARD NUMBER. Checkout happens on Razorpay's own surface and the callback
 * returns identifiers plus a signature. Keeping it that way is what keeps this application
 * entirely outside PCI scope — and it is why there is no card field anywhere in this
 * codebase.
 */
final class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    /**
     * @return array{gateway_order_id: string, amount_paise: int, currency: string, checkout: array<string, mixed>}
     */
    public function createOrder(Order $order): array
    {
        $response = $this->client()->post(self::API.'/orders', [
            // Razorpay works in the smallest currency unit, which is paise — the same unit
            // we store. No conversion, so no rounding, so no money quietly lost.
            'amount' => $order->total_paise,
            'currency' => $order->currency,
            'receipt' => $order->number,
            'notes' => [
                'order_uuid' => $order->uuid,
                'user_id' => (string) $order->user_id,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Could not create payment order: '.$response->body());
        }

        $gatewayOrderId = (string) $response->json('id');

        return [
            'gateway_order_id' => $gatewayOrderId,
            'amount_paise' => $order->total_paise,
            'currency' => $order->currency,
            'checkout' => [
                'key' => config('commerce.gateway.key_id'),
                'order_id' => $gatewayOrderId,
                'name' => 'Sadhana',
                'description' => data_get($order->item_snapshot, 'name', 'Sadhana'),
                'prefill' => ['contact' => $order->user?->phone],
                'theme' => ['color' => '#0F6B4F'],
            ],
        ];
    }

    /**
     * Verify the checkout callback signature.
     *
     * Called BEFORE any state change. An unverified callback is just an attacker claiming
     * to have paid, and a subscription granted on one is a free product with extra steps.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        if (! is_string($orderId) || ! is_string($paymentId) || ! is_string($signature)) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $orderId.'|'.$paymentId,
            (string) config('commerce.gateway.key_secret'),
        );

        // hash_equals, not ===. String comparison short-circuits on the first differing
        // byte and leaks the prefix length through timing.
        return hash_equals($expected, $signature);
    }

    /**
     * Verify a webhook against the RAW request body.
     *
     * The raw body, never a re-encoded array: re-serialising JSON changes key order and
     * whitespace, the computed hash differs, and every webhook silently fails verification.
     */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = config('commerce.gateway.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, (string) $secret), $signature);
    }

    /**
     * Ask the gateway what actually happened.
     *
     * Never trust a client-reported status. The browser can be told anything; only the
     * provider knows whether money moved.
     *
     * @return array{status: string, amount_paise: int, method: string|null, raw: array<string, mixed>}
     */
    public function fetchPayment(string $gatewayPaymentId): array
    {
        $response = $this->client()->get(self::API.'/payments/'.$gatewayPaymentId);

        if ($response->failed()) {
            throw new RuntimeException('Could not fetch payment: '.$response->body());
        }

        $body = $response->json();

        return [
            'status' => (string) ($body['status'] ?? 'failed'),
            'amount_paise' => (int) ($body['amount'] ?? 0),
            'method' => $body['method'] ?? null,
            'raw' => $body,
        ];
    }

    public function refund(Payment $payment, int $amountPaise, string $reason): string
    {
        $response = $this->client()->post(self::API.'/payments/'.$payment->gateway_payment_id.'/refund', [
            'amount' => $amountPaise,
            'notes' => ['reason' => $reason],
            // Queue rather than fail when the merchant balance is short. A refund that
            // errors because of our cash position is our problem, not the student's.
            'speed' => 'normal',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Refund failed: '.$response->body());
        }

        return (string) $response->json('id');
    }

    /**
     * Idempotent by design: a user cancelling twice, or a retry after a timeout, must not
     * error.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool
    {
        $response = $this->client()->post(self::API.'/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
            'cancel_at_cycle_end' => 0,
        ]);

        if ($response->successful()) {
            return true;
        }

        // Already cancelled reads as success — the desired end state is the same.
        return str_contains((string) $response->body(), 'already been cancelled');
    }

    private function client(): PendingRequest
    {
        $key = config('commerce.gateway.key_id');
        $secret = config('commerce.gateway.key_secret');

        if (blank($key) || blank($secret)) {
            throw new RuntimeException('Razorpay credentials are not configured.');
        }

        return Http::withBasicAuth((string) $key, (string) $secret)->timeout(30);
    }
}
