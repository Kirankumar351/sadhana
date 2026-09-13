<?php

declare(strict_types=1);

namespace App\Services\Push\Channels;

use App\Models\PushToken;
use App\Models\User;
use App\Services\Push\Contracts\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Web push via Firebase Cloud Messaging.
 *
 * Free, works inside the PWA, and reaches anyone who granted permission. This is the
 * default channel for everything, with WhatsApp reserved for the messages that genuinely
 * justify a per-message cost.
 *
 * DEAD TOKENS ARE DEACTIVATED, NOT DELETED. A user who reinstalls gets a new token and the
 * old row is evidence of what happened; deleting it loses the ability to tell "never
 * subscribed" from "subscribed and then uninstalled", which is a real retention signal.
 */
final class WebPushChannel implements PushChannel
{
    public function key(): string
    {
        return 'web';
    }

    public function canReach(User $user): bool
    {
        return $user->pushTokens()->where('is_active', true)->exists();
    }

    public function send(User $user, PushMessage $message): bool
    {
        $tokens = $user->pushTokens()->where('is_active', true)->get();

        if ($tokens->isEmpty()) {
            return false;
        }

        $serverKey = config('services.fcm.server_key');

        if (blank($serverKey)) {
            Log::warning('push.web.unconfigured', ['user' => $user->id]);

            return false;
        }

        $delivered = false;

        foreach ($tokens as $token) {
            if ($this->deliver($serverKey, $token, $message)) {
                $delivered = true;
            }
        }

        return $delivered;
    }

    private function deliver(string $serverKey, PushToken $token, PushMessage $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(15)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token->token,
                    'notification' => array_filter([
                        'title' => $message->title,
                        'body' => $message->trimmedBody(),
                        'image' => $message->imageUrl,
                        'icon' => '/icons/icon-192.png',
                        // A tag per type means a second notification of the same kind
                        // REPLACES the first in the tray rather than stacking. Three
                        // unread job alerts read as spam; one that says "3 new" does not.
                        'tag' => $message->type,
                    ]),
                    'data' => [
                        'url' => $message->url,
                        'type' => $message->type,
                    ],
                    // Time-sensitive content should not arrive stale. A deadline reminder
                    // delivered two days late is worse than useless.
                    'time_to_live' => 60 * 60 * 12,
                ]);

            if ($response->failed()) {
                Log::warning('push.web.failed', ['status' => $response->status()]);

                return false;
            }

            $body = $response->json();

            // FCM reports per-token failures inside a 200 response.
            if (($body['failure'] ?? 0) > 0) {
                $error = data_get($body, 'results.0.error');

                if (in_array($error, ['NotRegistered', 'InvalidRegistration'], true)) {
                    $token->update(['is_active' => false]);
                }

                return false;
            }

            $token->update(['last_used_at' => now()]);

            return true;
        } catch (Throwable $e) {
            // One dead token must never abort a broadcast to fifty thousand others.
            report($e);

            return false;
        }
    }
}
