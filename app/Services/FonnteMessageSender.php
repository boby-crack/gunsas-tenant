<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FonnteMessageSender
{
    public function send(?string $target, string $message): bool
    {
        if (! config('services.fonnte.auto_reply')) {
            Log::info('Fonnte auto-reply skipped: disabled');

            return false;
        }

        $token = config('services.fonnte.token');

        if (blank($token) || blank($target)) {
            Log::warning('Fonnte auto-reply skipped: missing token or target', [
                'has_token' => filled($token),
                'target' => $target,
            ]);

            return false;
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => $token,
                ])
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->post(rtrim(config('services.fonnte.base_url'), '/') . '/send', [
                    'target' => $target,
                    'message' => $message,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Fonnte auto-reply connection failed', [
                'target' => $target,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($response->failed() || $response->json('status') === false) {
            Log::warning('Fonnte auto-reply failed', [
                'target' => $target,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('Fonnte auto-reply queued', [
            'target' => $target,
            'status' => $response->status(),
            'requestid' => $response->json('requestid'),
            'message_id' => $response->json('id'),
        ]);

        return true;
    }
}
