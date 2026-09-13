<?php

declare(strict_types=1);

namespace App\Services\Push\Channels;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Push\Contracts\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp Cloud API.
 *
 * The highest open-rate channel in AP and TS by a wide margin, and the reason Vol 1 treats
 * WhatsApp as 25% of acquisition rather than an afterthought. It is also the only channel
 * that costs money per message and the only one that can get the business account
 * restricted if used carelessly.
 *
 * THREE RULES THAT ARE NOT NEGOTIABLE:
 *
 *   1. OPT-IN ONLY, never a default. Meta treats unsolicited business messages as a
 *      quality violation, and enough of them restricts the number for everyone.
 *
 *   2. TEMPLATE ONLY. Outside a 24-hour customer service window, only a pre-approved
 *      template may be sent. A message without one is rejected, and repeated rejections
 *      count against quality rating.
 *
 *   3. RESERVED FOR MESSAGES THAT EARN IT. A deadline reminder justifies the cost and the
 *      intrusion. A streak nudge does not — it goes over free web push or not at all.
 */
final class WhatsAppChannel implements PushChannel
{
    private const API_VERSION = 'v21.0';

    /** Types allowed on this channel, in descending order of justification. */
    private const ALLOWED_TYPES = ['deadline', 'new_notification', 'daily_quiz'];

    public function key(): string
    {
        return 'whatsapp';
    }

    public function canReach(User $user): bool
    {
        if (blank($user->phone) || $user->phone_verified_at === null) {
            return false;
        }

        // Explicit opt-in on at least one type. Never inferred from having a phone number.
        return NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('whatsapp', true)
            ->exists();
    }

    public function send(User $user, PushMessage $message): bool
    {
        if (! in_array($message->type, self::ALLOWED_TYPES, true)) {
            return false;
        }

        if ($message->whatsappTemplate === null) {
            Log::warning('push.whatsapp.no_template', ['type' => $message->type]);

            return false;
        }

        if (! $this->optedIn($user, $message->type)) {
            return false;
        }

        $phoneId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        if (blank($phoneId) || blank($token)) {
            Log::warning('push.whatsapp.unconfigured');

            return false;
        }

        try {
            $response = Http::withToken((string) $token)
                ->timeout(20)
                ->post('https://graph.facebook.com/'.self::API_VERSION."/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->e164($user->phone),
                    'type' => 'template',
                    'template' => [
                        'name' => $message->whatsappTemplate,
                        // Templates are approved per language. Falling back to English for
                        // a Telugu user would defeat the entire point of the channel.
                        'language' => ['code' => $this->languageCode($message->locale)],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(
                                static fn (string $p): array => ['type' => 'text', 'text' => $p],
                                $message->templateParams,
                            ),
                        ]],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('push.whatsapp.failed', [
                    'status' => $response->status(),
                    'error' => data_get($response->json(), 'error.message'),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function optedIn(User $user, string $type): bool
    {
        return NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('whatsapp', true)
            ->exists();
    }

    /**
     * India only, for now. Stored numbers are ten digits without a country code, which is
     * how every user types their own number.
     */
    private function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;

        return str_starts_with($digits, '91') ? $digits : '91'.$digits;
    }

    private function languageCode(string $locale): string
    {
        return match ($locale) {
            'te' => 'te',
            'hi' => 'hi',
            'ta' => 'ta',
            default => 'en',
        };
    }
}
