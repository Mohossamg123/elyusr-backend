<?php
/* 17 Nov 2026: reviewers can edit a pending change before approving it; gold/silver prices for the zakat calculator. Loaded from the last line of routes/api.php. */

use App\Http\Controllers\Api\V1\Extras\ChangeRequestEditController;
use App\Http\Controllers\Api\V1\Extras\MetalPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::patch('admin/change-requests/{id}/payload', [ChangeRequestEditController::class, 'update'])
        ->whereNumber('id')->middleware('permission:content_change_requests.approve');
    Route::get('admin/change-requests/{id}/revisions', [ChangeRequestEditController::class, 'revisions'])
        ->whereNumber('id')->middleware('permission:content_change_requests.view');
});

Route::prefix('v1')->middleware('throttle:30,1')->group(function () {
    Route::get('public/metal-prices', MetalPriceController::class);
});
