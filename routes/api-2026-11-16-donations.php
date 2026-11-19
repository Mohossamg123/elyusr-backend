<?php
/* 16 Nov 2026: «تم الاطلاع» on a donation. Loaded from the last line of routes/api.php. */

use App\Http\Controllers\Api\V1\Donation\DonationReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::patch('donations/{donation}/reviewed', DonationReviewController::class)->middleware('permission:donations.update');
});
