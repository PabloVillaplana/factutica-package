<?php

use Illuminate\Support\Facades\Route;
use FactuTica\FactuticaCR\Http\Controllers\ReceiptController;
use FactuTica\FactuticaCR\Http\Controllers\ReceptionController;
use FactuTica\FactuticaCR\Http\Controllers\WebhookController;

// Rutas de la API (protegidas por middleware configurable)
Route::prefix('invoicing-cr')
    ->middleware(config('invoicing-cr.invoicing.middleware.api', []))
    ->group(function () {

        // Comprobantes
        Route::post('/receipts', [ReceiptController::class, 'store']);
        Route::get('/receipts', [ReceiptController::class, 'index']);
        Route::get('/receipts/key/{uiKey}', [ReceiptController::class, 'showByKey']);
        Route::get('/receipts/key/{uiKey}/status', [ReceiptController::class, 'status']);
        Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);

        // Recepción de documentos
        Route::post('/reception', [ReceptionController::class, 'store']);

    });

// Webhook de Hacienda (middleware separado — normalmente sin auth)
Route::prefix('invoicing-cr')
    ->middleware(config('invoicing-cr.invoicing.middleware.webhook', []))
    ->group(function () {

        Route::post('/webhook', [WebhookController::class, 'handle']);

    });