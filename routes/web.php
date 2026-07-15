<?php

use Illuminate\Support\Facades\Route;
use App\Services\BotIntelligence;
use App\Http\Controllers\OwnerBusinessReportController;
use App\Http\Controllers\WhatsappWebhookController;

Route::get('/', function () {
    return redirect('/admin/login');
});


Route::get('/test-ai', function () {
    $bot = new BotIntelligence();
    return $bot->parsePesan("Retur durian monthong kode d-123 berat 5kg warna merah");
});

Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'store']);

Route::middleware('auth')->get('/reports/owner-business', [OwnerBusinessReportController::class, 'print'])
    ->name('reports.owner-business.print');
