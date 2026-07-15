<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'livewire/update',
        'livewire/*',
        'admin/livewire/update',
        '*/livewire/update',
        'webhook/whatsapp',
        'api/whatsapp/webhook',
    ];
}
