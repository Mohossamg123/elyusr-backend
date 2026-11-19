<?php

namespace App\Http\Controllers\Api\V1\DonationCampaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\DonationCampaign\StoreDonationCampaignRequest;
use App\Http\Requests\DonationCampaign\UpdateDonationCampaignRequest;
use App\Http\Resources\DonationCampaignResource;
use App\Models\DonationCampaign;
use App\Models\Donation;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DonationCampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = DonationCampaign::with([
            'category',
            'benefits',
            'donationPackages',
            'cases',
            'faqCategory',
        ])
        ->when(
            $request->filled('category_id'),
            fn ($query) =>
                $query->where(
                    'category_id',
                    $request->category_id
                )
        )
        ->when(
            $request->has('is_active'),
            fn ($query) =>
                $query->where(
                    'is_active',
                    $request->boolean('is_active')
                )
        )
        ->when(
            $request->has('donation_available'),
            fn ($query) =>
                $query->where(
                    'donation_available',
                    $request->boolean('donation_available')
                )
        )

        ->orderBy('sort_order')
        ->orderByDesc('id')
        ->paginate(
    min(max($request->integer('per_page', 15), 1), 50)
);        
        $paidAmounts = Donation::query()
    ->whereIn('donation_campaign_id', $campaigns->pluck('id'))
    ->where('status', 'paid')
    ->selectRaw('donation_campaign_id, SUM(amount) as total')
    ->groupBy('donation_campaign_id')
    ->pluck('total', 'donation_campaign_id');

$campaigns->getCollection()->each(function ($campaign) use ($paidAmounts) {
    $campaign->setAttribute(
        'paid_donations_total',
        (float) ($paidAmounts[$campaign->id] ?? 0)
    );
});

        return DonationCampaignResource::collection($campaigns);
    }

public function store(
    StoreDonationCampaignRequest $request,
    ActivityLogService $activityLog
) {
    $data = $request->validated();

    if ($request->hasFile('image')) {
        $data['image'] = $request
            ->file('image')
            ->store('donation-campaigns', 'public');
    }

    $campaign = DonationCampaign::create($data);

    $activityLog->created(
        $campaign,
        'تم إنشاء حملة تبرع: ' . $campaign->name_ar,
        [
            'slug' => $campaign->slug,
            'name_ar' => $campaign->name_ar,
            'name_en' => $campaign->name_en,
        ]
    );

    return response()->json([
        'message' => 'تم إنشاء حملة التبرع بنجاح',
        'data' => new DonationCampaignResource(
            $campaign->load([
                'category',
                'benefits',
                'donationPackages',
                'cases',
            ])
        ),
    ], 201);
}
    public function showBySlug(string $slug)
    {
        return $this->show(DonationCampaign::where('slug', $slug)->orWhere('landing_slug', $slug)->firstOrFail());
    }

    public function show(DonationCampaign $donationCampaign)
    {
        return new DonationCampaignResource(
            $donationCampaign->load([
                'category',
                'benefits',
                'donationPackages',
                'cases',
            ])
        );
    }

  public function update(
    UpdateDonationCampaignRequest $request,
    DonationCampaign $donationCampaign,
    ActivityLogService $activityLog
) {
    $oldValues = $donationCampaign->only([
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'target_amount',
        'minimum_amount',
        'donation_available',
        'is_active',
        'category_id',
        'image',
    ]);

    $data = $request->validated();

    if ($request->hasFile('image')) {

        if ($donationCampaign->image && str_starts_with(ltrim($donationCampaign->image, '/'), 'donation-campaigns/')) {
            Storage::disk('public')->delete($donationCampaign->image);
        }

        $data['image'] = $request
            ->file('image')
            ->store('donation-campaigns', 'public');
    }

    $donationCampaign->update($data);

    $activityLog->updated(
        $donationCampaign,
        'تم تحديث حملة التبرع: ' . $donationCampaign->name_ar,
        [
            'before' => $oldValues,
            'after' => $donationCampaign->only(array_keys($oldValues)),
        ]
    );

    return response()->json([
        'message' => 'تم تحديث حملة التبرع بنجاح',
        'data' => new DonationCampaignResource(
            $donationCampaign->load([
                'category',
                'benefits',
                'donationPackages',
                'cases',
            ])
        ),
    ]);
}
  
public function destroy(
    DonationCampaign $donationCampaign,
    ActivityLogService $activityLog
) {
    $activityLog->deleted(
        $donationCampaign,
        'تم حذف حملة التبرع: ' . $donationCampaign->name_ar,
        [
            'name_ar' => $donationCampaign->name_ar,
            'name_en' => $donationCampaign->name_en,
            'slug' => $donationCampaign->slug,
            'category_id' => $donationCampaign->category_id,
        ]
    );

        if ($donationCampaign->image && str_starts_with(ltrim($donationCampaign->image, '/'), 'donation-campaigns/')) {
            Storage::disk('public')->delete($donationCampaign->image);
        }

    $donationCampaign->delete();

    return response()->json([
        'message' => 'تم حذف حملة التبرع بنجاح',
    ]);
}
}