<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OwnerBusinessReportController;
use App\Http\Controllers\WhatsappWebhookController;

Route::get('/', function () {
    return redirect('/admin/login');
});


Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'store']);

Route::middleware('auth')->get('/reports/owner-business', [OwnerBusinessReportController::class, 'print'])
    ->name('reports.owner-business.print');
