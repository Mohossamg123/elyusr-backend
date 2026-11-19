<?php
/*
 | 16 Nov 2026: misbaha and accounts (own tally, my shared misbahas). Loaded from the last line of routes/api.php.
 */

use App\Http\Controllers\Api\V1\Misbaha\MisbahaMeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('misbaha/count', [MisbahaMeController::class, 'count'])->middleware('throttle:120,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me/misbaha', [MisbahaMeController::class, 'mine']);
        Route::patch('me/misbaha-rooms/{slug}', [MisbahaMeController::class, 'updateRoom'])->middleware('throttle:30,1');
        Route::get('admin/misbaha/overview', [MisbahaMeController::class, 'adminOverview'])->middleware('permission:content.view');
    });
});
