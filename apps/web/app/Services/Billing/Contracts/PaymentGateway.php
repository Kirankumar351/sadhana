<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Order;
use App\Models\Payment;

/**
 * Payment provider boundary.
 *
 * Razorpay is the shipped implementation because UPI is how this audience actually pays:
 * card penetration in the target segment is low, and UPI has no per-transaction floor that
 * would make a Rs 199 product uneconomic.
 *
 * The interface exists so that is a reversible decision. Gateways change their terms,
 * their fees and occasionally their appetite for a category, and none of that should reach
 * further into the codebase than one class.
 *
 * WE NEVER SEE A CARD NUMBER. Checkout happens on the provider's own surface and the
 * callback returns identifiers and a signature. Keeping it that way is what keeps this
 * application outside PCI scope entirely.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Create the provider-side order and return what the client checkout needs.
     *
     * @return array{gateway_order_id: string, amount_paise: int, currency: string, checkout: array<string, mixed>}
     */
    public function createOrder(Order $order): array;

    /**
     * Verify a callback signature.
     *
     * Called before ANY state change. An unverified callback is an attacker claiming to
     * have paid, and a subscription granted on one is a free product with extra steps.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool;

    /**
     * Verify a webhook signature against the raw request body.
     *
     * The RAW body, not a re-encoded array: re-serialising JSON changes key order and
     * whitespace, and the signature will not match.
     */
    public function verifyWebhook(string $rawBody, string $signature): bool;

    /**
     * Ask the provider what actually happened, rather than trusting the client.
     *
     * @return array{status: string, amount_paise: int, method: string|null, raw: array<string, mixed>}
     */
    public function fetchPayment(string $gatewayPaymentId): array;

    public function refund(Payment $payment, int $amountPaise, string $reason): string;

    /**
     * Cancel a recurring mandate. Must be idempotent — a user cancelling twice, or a retry
     * after a timeout, cannot be allowed to error.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool;
}
