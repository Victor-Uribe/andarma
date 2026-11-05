<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AndarmaFinanceController as Finance;
use App\Http\Middleware\EnsureAndarmaAdmin;

Route::prefix('admin/finance')->middleware(['auth:sanctum', EnsureAndarmaAdmin::class])->group(function () {
    Route::get('/payments', [Finance::class, 'index']);
    Route::get('/lots', [Finance::class, 'lots']);
    Route::get('/lots/{lotId}', [Finance::class, 'summary'])->whereNumber('lotId');
    Route::put('/lots/{lotId}/terms', [Finance::class, 'saveTerms'])->whereNumber('lotId');
    Route::post('/lots/{lotId}/payments', [Finance::class, 'storePayment'])->whereNumber('lotId')->middleware('throttle:20,1');
    Route::post('/payments/{paymentId}/cancel', [Finance::class, 'cancelPayment'])->whereNumber('paymentId')->middleware('throttle:10,1');
});
