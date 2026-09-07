<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OwnerBusinessReportController;
use App\Http\Controllers\StockSnapshotExportController;
use App\Http\Controllers\WhatsappWebhookController;

Route::get('/', function () {
    return redirect('/admin/login');
});


Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'store']);

Route::middleware('auth')->get('/reports/owner-business', [OwnerBusinessReportController::class, 'print'])
    ->name('reports.owner-business.print');

Route::middleware('auth')->get('/reports/stock-snapshot/export', StockSnapshotExportController::class)
    ->name('reports.stock-snapshot.export');
