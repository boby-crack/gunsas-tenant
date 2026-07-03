<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteMessageSender
{
    public function send(?string $target, string $message): bool
    {
        if (! config('services.fonnte.auto_reply')) {
            return false;
        }

        $token = config('services.fonnte.token');

        if (blank($token) || blank($target)) {
            return false;
        }

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->timeout(15)
            ->post(rtrim(config('services.fonnte.base_url'), '/') . '/send', [
                'target' => $target,
                'message' => $message,
            ]);

        if ($response->failed() || $response->json('status') === false) {
            Log::warning('Fonnte auto-reply failed', [
                'target' => $target,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
