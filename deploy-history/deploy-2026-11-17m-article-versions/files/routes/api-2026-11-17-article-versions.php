<?php
/* 17 Nov 2026: article versions («النسخ السابقة») for the blog editor. Loaded from the last line of routes/api.php. */

use App\Http\Controllers\Api\V1\Extras\BlogRevisionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('admin/blog-revisions', [BlogRevisionController::class, 'store']);
    Route::get('admin/blog-revisions', [BlogRevisionController::class, 'index']);
});
