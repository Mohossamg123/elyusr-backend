<?php

namespace App\Http\Controllers\Api\V1\Donation;

use App\Http\Controllers\Controller;
use App\Http\Resources\DonationResource;
use App\Models\Donation;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

/**
 * PATCH /api/v1/donations/{donation}/reviewed  { reviewed: bool }
 * «تم الاطلاع/الرد»: a team member marks a donation as seen so nobody has to check it twice.
 */
class DonationReviewController extends Controller
{
    public function __invoke(Request $request, Donation $donation, ActivityLogService $log)
    {
        $reviewed = $request->validate(['reviewed' => ['required', 'boolean']])['reviewed'];

        $donation->forceFill([
            'reviewed_at' => $reviewed ? now() : null,
            'reviewed_by' => $reviewed ? $request->user()->id : null,
        ])->save();

        $log->custom('donation_reviewed', $donation, ($reviewed ? 'تم الاطلاع على' : 'إلغاء الاطلاع على') . " التبرع {$donation->transaction_reference}", ['reviewed' => $reviewed]);

        return response()->json(['data' => new DonationResource($donation->fresh()->load(['donor', 'paymentMethod', 'program', 'case', 'donationPackage', 'project', 'donationCampaign']))]);
    }
}
