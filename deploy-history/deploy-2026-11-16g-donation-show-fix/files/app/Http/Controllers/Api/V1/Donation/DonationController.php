<?php

namespace App\Http\Controllers\Api\V1\Donation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donation\StoreDonationRequest;
use App\Http\Requests\Donation\UpdateDonationRequest;
use App\Http\Resources\DonationResource;
use App\Models\Donation;
use App\Models\Donor;
use App\Support\DonorResolver;
use App\Support\PaymentMethodKind;
use App\Support\DonationFilters;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Notifications\NewDonationNotification;
use App\Services\KashierService;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DonationController extends Controller
{
   public function index(Request $request)
{
    $query = Donation::with([
        'donor',
        'paymentMethod',
        'program',
        'case',
        'donationPackage',
        'project',
        'donationCampaign',
    ]);

    if ($request->boolean('mine') || !$request->user()->roles()->whereIn('slug', ['super-admin', 'admin'])->exists()) {
        $query->whereIn('donor_id', \App\Support\DeliverySteps::donorIdsOf($request->user()));
    }

    DonationFilters::apply($query, $request);

    $sort = (string) $request->input('sort', '-created_at');
    $column = ltrim($sort, '-');

    $query->orderBy(
        in_array($column, ['created_at', 'amount'], true) ? $column : 'created_at',
        str_starts_with($sort, '-') ? 'desc' : 'asc'
    )->orderByDesc('id');

    $perPage = min(100, max(1, (int) $request->input('per_page', 10)));

    return DonationResource::collection(
        $query->paginate($perPage)->withQueryString()
    );
}
    
    public function store(
        StoreDonationRequest $request,
        PaymobService $paymobService,
        KashierService $kashierService
    ) {
        $data = $request->validated();

        $data += DonorResolver::snapshot($data['name'] ?? null, $data['phone'] ?? null);

        $data['donor_id'] = DonorResolver::resolve(
            $request->user('sanctum'),
            $data['name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
        )->id;

        do {
            $transactionReference =
                'YSR-' .
                strtoupper(Str::random(8)) .
                '-' .
                random_int(1000, 9999);
        } while (
            Donation::where(
                'transaction_reference',
                $transactionReference
            )->exists()
        );

        $data['transaction_reference'] = $transactionReference;
        $data['status'] = 'pending';

  
        if ($request->hasFile('payment_receipt')) {
            $data['payment_receipt'] = $request
                ->file('payment_receipt')
                ->store('donations/receipts', 'public');
        }

        $data['tracking_data'] = [
            'created_at' => now()->toISOString(),
        ];

        
           unset(
                 $data['name'],
                 $data['email'],
                 $data['phone'],
                 $data['address'],
                 $data['_donation_target'],
                 $data['payment_reference'],
                 $data['payment_details']
                );


        $donation = Donation::create($data);

        $donation->load([
            'donor',
            'paymentMethod',
        ]);

if (
    !$donation->paymentMethod ||
    !$donation->paymentMethod->is_active
) {
    $this->markPaymentFailed(
        $donation,
        'Invalid or disabled payment method.'
    );

    return response()->json([
        'message' => 'Selected payment method is invalid or disabled.',
        'transaction_reference' => $donation->transaction_reference,
    ], 422);
}

if (!PaymentMethodKind::isGateway($donation)) {
    return response()->json([
        'message' => 'Donation created successfully',

        'data' => [
            'donation' => new DonationResource(
                $donation->load([
                    'donor',
                    'paymentMethod',
                    'program',
                    'case',
                    'donationPackage',
                    'project',
                    'donationCampaign',
                ])
            ),

            'payment' => null,
        ],
    ], 201);
}

        $provider = strtolower(
            trim(
                $donation->paymentMethod?->provider_en ?? ''
            )
        );


        if (
            !$donation->paymentMethod ||
            !$provider
        ) {
            $this->markPaymentFailed(
                $donation,
                'Invalid payment method or provider.'
            );

            return response()->json([
                'message' =>
                    'Selected payment method is invalid.',
                'transaction_reference' =>
                    $donation->transaction_reference,
            ], 422);
        }

        if (
            !in_array(
                $provider,
                ['paymob', 'kashier'],
                true
            )
        ) {
            $this->markPaymentFailed(
                $donation,
                'Unsupported payment provider: ' . $provider
            );

            return response()->json([
                'message' =>
                    'Selected payment provider is not supported.',
                'transaction_reference' =>
                    $donation->transaction_reference,
            ], 422);
        }

        $gateway = PaymentGatewaySetting::where(
            'gateway',
            $provider
        )->first();

        if (!$gateway) {
            $this->markPaymentFailed(
                $donation,
                ucfirst($provider) .
                    ' payment gateway is not configured.'
            );

            return response()->json([
                'message' =>
                    ucfirst($provider) .
                    ' payment gateway is not configured.',
                'transaction_reference' =>
                    $donation->transaction_reference,
            ], 422);
        }

        if (!$gateway->is_active) {
            $this->markPaymentFailed(
                $donation,
                ucfirst($provider) .
                    ' payment gateway is disabled.'
            );

            return response()->json([
                'message' =>
                    ucfirst($provider) .
                    ' payment gateway is currently disabled.',
            ], 422);
        }

        if (!$donation->paymentMethod->is_active) {
            $this->markPaymentFailed(
                $donation,
                'Selected payment method is disabled.'
            );

            return response()->json([
                'message' =>
                    'Selected payment method is currently disabled.',
            ], 422);
        }

        $payment = [];


        try {
            if ($provider === 'paymob') {
                $paymobResponse =
                    $paymobService
                        ->createPaymentIntention(
                            $donation
                        );

                $clientSecret =
                    $paymobResponse['client_secret']
                    ?? null;

                if (
                    !is_string($clientSecret) ||
                    trim($clientSecret) === ''
                ) {
                    throw new \RuntimeException(
                        'Paymob did not return a valid client secret.'
                    );
                }

                $payment = [
                    'provider' => 'paymob',
                    'client_secret' => $clientSecret,
                ];
            }

            if ($provider === 'kashier') {
                $kashierResponse =
                    $kashierService
                        ->createPaymentSession(
                            $donation
                        );

                $sessionUrl =
                    $kashierResponse['sessionUrl']
                    ?? null;

                if (
                    !is_string($sessionUrl) ||
                    trim($sessionUrl) === ''
                ) {
                    throw new \RuntimeException(
                        'Kashier did not return a valid session URL.'
                    );
                }

                $payment = [
                    'provider' => 'kashier',
                    'session_url' => $sessionUrl,
                    'session_id' =>
                        $kashierResponse['_id']
                        ?? null,
                ];
            }
        } catch (Throwable $e) {
            report($e);

            $this->markPaymentFailed(
                $donation,
                'Payment initialization failed.',
                [
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' =>
                    'Unable to initialize payment.',
                'transaction_reference' =>
                    $donation->transaction_reference,
            ], 502);
        }
        return response()->json([
            'message' =>
                'Donation created successfully',

            'data' => [
                'donation' =>
                    new DonationResource(
                        $donation->load([
                            'donor',
                            'paymentMethod',
                            'program',
                            'case',
                            'donationPackage',
                            'project',
                            'donationCampaign',
                        ])
                    ),

                'payment' => $payment,
            ],
        ], 201);
    }
    
    public function show(Request $request, Donation $donation)
    {
         if (! \App\Support\StaffAccess::seesAllDonations($request->user())) {
            $mine = collect(\App\Support\DeliverySteps::donorIdsOf($request->user()))
                ->map(fn ($id) => (int) $id);
            abort_unless($mine->contains((int) $donation->donor_id), 403, 'Forbidden');
        }
        return new DonationResource(
            $donation->load([
                'donor',
                'paymentMethod',
                'program',
                'case',
                'donationPackage',
                'project',
                'donationCampaign',
            ])
        );
    }

    public function update(
        UpdateDonationRequest $request,
        Donation $donation
    ) {
        $data = $request->validated();
        
        
        
unset(
    $data['payment_reference'],
    $data['payment_details']
);

    unset(
        $data['amount'],
        $data['payment_method_id'],
        $data['program_id'],
        $data['case_id'],
        $data['project_id'],
        $data['donation_campaign_id'],
        $data['donation_package_id']
    );

        if (
            isset($data['status']) &&
            $data['status'] !== $donation->status
        ) {
            unset($data['status']);
        }


       if (
    $request->hasFile('payment_receipt') &&
    !in_array($donation->status, ['paid', 'refunded'], true)
) {
    if ($donation->payment_receipt) {
        Storage::disk('public')->delete(
            $donation->payment_receipt
        );
    }

    $data['payment_receipt'] = $request
        ->file('payment_receipt')
        ->store(
            'donations/receipts',
            'public'
        );
}

        $donation->update($data);

        return new DonationResource(
            $donation->load([
                'donor',
                'paymentMethod',
                'program',
                'case',
                'donationPackage',
                'project',
                'donationCampaign',
            ])
        );
    }

  public function destroy(Request $request, Donation $donation, \App\Services\ActivityLogService $log)
{
    // Only donations that never received money can go (test, failed, cancelled, abandoned).
    // Paid or refunded ones are kept: they are accounting records.
    if (! in_array($donation->status, ['pending', 'failed', 'cancelled'], true)) {
        return response()->json([
            'message' => 'التبرعات المدفوعة أو المستردة ما بتتحذفش. غيّر الحالة الأول لو ده تبرع تجريبي.',
        ], 422);
    }

    // The caller must type the donation's own reference: no accidental or scripted deletes.
    if ((string) $request->input('confirm_reference') !== (string) $donation->transaction_reference) {
        return response()->json([
            'message' => 'اكتب رقم التبرع بالظبط للتأكيد.',
        ], 422);
    }

    // Soft delete: the row and its receipt file stay, the activity log says who and why.
    $log->custom('donation_deleted', $donation, "حذف التبرع {$donation->transaction_reference} ({$donation->status})", [
        'status' => $donation->status,
        'amount' => (string) $donation->amount,
        'reason' => substr((string) $request->input('reason', ''), 0, 300) ?: null,
    ]);

    $donation->delete();

    return response()->json([
        'message' => 'Deleted Successfully',
    ]);
}

    public function track(string $transactionReference)
    {
        $donation = Donation::with([
            'program',
            'case',
            'donationPackage',
            'project',
            'donationCampaign',
            'paymentMethod',
        ])
            ->where(
                'transaction_reference',
                $transactionReference
            )
            ->first();

        if (!$donation) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        return response()->json([
            'transaction_reference' =>
                $donation->transaction_reference,

            'status' =>
                $donation->status,

            'delivery' =>
                \App\Support\DeliverySteps::of($donation),

            'amount' =>
                $donation->amount,

            'program' => $donation->program
                ? [
                    'id' =>
                        $donation->program->id,

                    'name' =>
                        $donation->program->name,

                    'name_ar' =>
                        $donation->program->name_ar,

                    'name_en' =>
                        $donation->program->name_en,
                ]
                : null,

            'case' => $donation->case
                ? [
                    'id' =>
                        $donation->case->id,

                    'code' =>
                        $donation->case->code,

                    'title' =>
                        $donation->case->title,

                    'program_id' =>
                        $donation->case->program_id,
                ]
                : null,

            'project' => $donation->project
                ? [
                    'id' =>
                        $donation->project->id,

                    'name' =>
                        $donation->project->name,
                ]
                : null,

            'donation_campaign' =>
                $donation->donationCampaign
                    ? [
                        'id' =>
                            $donation
                                ->donationCampaign
                                ->id,

                        'name_ar' =>
                            $donation
                                ->donationCampaign
                                ->name_ar,

                        'name_en' =>
                            $donation
                                ->donationCampaign
                                ->name_en,

                        'slug' =>
                            $donation
                                ->donationCampaign
                                ->slug,
                    ]
                    : null,

            'donation_package' =>
                $donation->donationPackage
                    ? [
                        'id' =>
                            $donation
                                ->donationPackage
                                ->id,

                        'name_ar' =>
                            $donation
                                ->donationPackage
                                ->name_ar,

                        'name_en' =>
                            $donation
                                ->donationPackage
                                ->name_en,

                        'amount' =>
                            $donation
                                ->donationPackage
                                ->amount,
                    ]
                    : null,

            'payment_method' =>
                $donation->paymentMethod
                    ? [
                        'id' =>
                            $donation
                                ->paymentMethod
                                ->id,

                        'name_ar' =>
                            $donation
                                ->paymentMethod
                                ->name_ar,

                        'name_en' =>
                            $donation
                                ->paymentMethod
                                ->name_en,

                        'slug' =>
                            $donation
                                ->paymentMethod
                                ->slug,
                    ]
                    : null,

            'created_at' =>
                $donation->created_at,
        ]);
    }

    protected function markPaymentFailed(
        Donation $donation,
        string $message,
        array $extra = []
    ): void {
        $trackingData = array_merge(
            $donation->tracking_data ?? [],
            [
                'payment_error' => $message,
                'failed_at' =>
                    now()->toISOString(),
            ],
            $extra
        );

        $donation->update([
            'status' => 'failed',
            'tracking_data' => $trackingData,
        ]);
    }
}