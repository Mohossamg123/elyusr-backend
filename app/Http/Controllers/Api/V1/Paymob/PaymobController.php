<?php

namespace App\Http\Controllers\Api\V1\Paymob;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\PaymentGatewaySetting;
use App\Models\Project;
use App\Models\Program;
use App\Models\User;
use App\Notifications\ProgramTargetReachedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymobController extends Controller
{
    public function callback(Request $request)
    {
        $data = $request->all();

        // Verify HMAC before trusting the callback.
        if (!$this->verifyHmac($data)) {
            return response()->json([
                'message' => 'Invalid HMAC.',
            ], 403);
        }

        $transaction = $data['obj'] ?? null;

        if (!is_array($transaction) || empty($transaction)) {
            return response()->json([
                'message' => 'Invalid callback data.',
            ], 400);
        }

        $transactionReference = data_get(
            $transaction,
            'order.merchant_order_id'
        );

        if (!$transactionReference) {
            return response()->json([
                'message' => 'Transaction reference not found.',
            ], 400);
        }

        $callbackAmount = (int) data_get(
            $transaction,
            'amount_cents',
            0
        );

        if ($callbackAmount <= 0) {
            return response()->json([
                'message' => 'Invalid payment amount.',
            ], 422);
        }

        $callbackCurrency = strtoupper((string) data_get(
            $transaction,
            'currency',
            ''
        ));

        if ($callbackCurrency !== 'EGP') {
            return response()->json([
                'message' => 'Payment currency mismatch.',
            ], 422);
        }

        $settings = $this->gatewaySettings();

        $configuredIntegrationId = data_get(
            $settings,
            'integration_id'
        );

        if (
            $configuredIntegrationId === null ||
            $configuredIntegrationId === ''
        ) {
            $configuredIntegrationId =
                config('services.paymob.integration_id');
        }

        $callbackIntegrationId = data_get(
            $transaction,
            'integration_id'
        );

        if (
            $configuredIntegrationId === null ||
            $configuredIntegrationId === '' ||
            $callbackIntegrationId === null ||
            $callbackIntegrationId === '' ||
            (string) $callbackIntegrationId !==
                (string) $configuredIntegrationId
        ) {
            return response()->json([
                'message' => 'Payment integration mismatch.',
            ], 422);
        }

        $transactionId = data_get($transaction, 'id');

        $success = filter_var(
            data_get($transaction, 'success', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $pending = filter_var(
            data_get($transaction, 'pending', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $refunded = filter_var(
            data_get($transaction, 'is_refunded', false),
            FILTER_VALIDATE_BOOLEAN
        );

        try {
            $result = DB::transaction(function () use (
                $transactionReference,
                $callbackAmount,
                $success,
                $pending,
                $refunded,
                $transaction,
                $transactionId
            ) {
                // Lock donation to prevent duplicate callback processing.
                $donation = Donation::where(
                    'transaction_reference',
                    $transactionReference
                )
                    ->lockForUpdate()
                    ->first();

                if (!$donation) {
                    return 'not_found';
                }

                // Database amount is the source of truth.
                $expectedAmount = (int) round(
                    ((float) $donation->amount) * 100
                );

                if ($callbackAmount !== $expectedAmount) {
                    return 'amount_mismatch';
                }

                // One Paymob transaction can belong to one donation only. merchant_order_id is not part of the
                // signed HMAC fields, so a genuine callback could otherwise be replayed against another pending
                // donation of the same amount. The transaction id is signed, so it is the binding.
                if (
                    $transactionId !== null
                    && (string) $transactionId !== ''
                    && Donation::where('payment_reference', (string) $transactionId)
                        ->where('id', '!=', $donation->id)
                        ->exists()
                ) {
                    return 'duplicate_transaction';
                }

                // Handle refunds before the final-state guard.
                if ($refunded) {
                    if ($donation->status === 'refunded') {
                        return 'already_final';
                    }

                    $trackingData = array_merge(
                        $donation->tracking_data ?? [],
                        [
                            'paymob_transaction_id' => $transactionId,
                            'payment_status' => 'refunded',
                            'refunded_at' => now()->toISOString(),
                            'updated_at' => now()->toISOString(),
                        ]
                    );

                    $donation->update([
                        'status' => 'refunded',
                        'payment_reference' => $transactionId
                            ? (string) $transactionId
                            : $donation->payment_reference,
                        'payment_details' => $transaction,
                        'tracking_data' => $trackingData,
                    ]);

                    return 'refunded';
                }

                // Never downgrade a final payment state.
                if (in_array(
                    $donation->status,
                    ['paid', 'refunded'],
                    true
                )) {
                    return 'already_final';
                }

                // Keep pending transactions pending.
                if ($pending) {
                    $trackingData = array_merge(
                        $donation->tracking_data ?? [],
                        [
                            'paymob_transaction_id' => $transactionId,
                            'payment_status' => 'pending',
                            'updated_at' => now()->toISOString(),
                        ]
                    );

                    $donation->update([
                        'payment_reference' => $transactionId
                            ? (string) $transactionId
                            : $donation->payment_reference,
                        'payment_details' => $transaction,
                        'tracking_data' => $trackingData,
                    ]);

                    return 'pending';
                }

                $newStatus = $success ? 'paid' : 'failed';

                $trackingData = array_merge(
                    $donation->tracking_data ?? [],
                    [
                        'paymob_transaction_id' => $transactionId,
                        'payment_status' => $newStatus,
                        'updated_at' => now()->toISOString(),
                    ]
                );

                $donation->update([
                    'status' => $newStatus,
                    'payment_reference' => $transactionId
                        ? (string) $transactionId
                        : $donation->payment_reference,
                    'payment_details' => $transaction,
                    'tracking_data' => $trackingData,
                ]);

                /*
                 * Update the program's collected amount only
                 * after successful payment.
                 */
                if ($newStatus === 'paid' && $donation->program_id) {
                    $program = Program::whereKey($donation->program_id)
                        ->lockForUpdate()
                        ->first();

                    if ($program) {
                        $oldCollected = (float) (
                            $program->collected_amount ?? 0
                        );

                        $newCollected = $oldCollected
                            + (float) $donation->amount;

                        $targetAmount = (float) (
                            $program->target_amount ?? 0
                        );

                        $program->collected_amount = $newCollected;
                        $program->save();

                        // Notify admins when this payment first reaches
                        // or crosses the program target.
                        if (
                            $targetAmount > 0 &&
                            $oldCollected < $targetAmount &&
                            $newCollected >= $targetAmount
                        ) {
                            $admins = User::whereHas(
                                'roles',
                                function ($query) {
                                    $query->where(
                                        'slug',
                                        'super-admin'
                                    );
                                }
                            )->get();

                            foreach ($admins as $admin) {
                                $admin->notify(
                                    new ProgramTargetReachedNotification(
                                        (int) $program->id,
                                        (string) (
                                            $program->name_ar
                                            ?: $program->name
                                            ?: 'البرنامج'
                                        ),
                                        (string) $targetAmount,
                                        (string) $newCollected
                                    )
                                );
                            }
                        }
                    }
                }

                // Preserve the existing project collected amount update.
                if ($newStatus === 'paid' && $donation->project_id) {
                    $project = Project::whereKey($donation->project_id)
                        ->lockForUpdate()
                        ->first();

                    if ($project) {
                        $project->collected_amount =
                            (float) ($project->collected_amount ?? 0)
                            + (float) $donation->amount;

                        $project->save();
                    }
                }

                return 'processed';
            });

            if ($result === 'not_found') {
                return response()->json([
                    'message' => 'Donation not found.',
                ], 404);
            }

            if ($result === 'amount_mismatch') {
                return response()->json([
                    'message' => 'Payment amount mismatch.',
                ], 422);
            }

            if ($result === 'duplicate_transaction') {
                report(new \RuntimeException('Paymob callback replayed against a different donation: ' . $transactionReference));

                return response()->json([
                    'message' => 'Transaction already belongs to another donation.',
                ], 409);
            }

            if ($result === 'already_final') {
                return response()->json([
                    'message' => 'Callback already processed.',
                ], 200);
            }

            if ($result === 'pending') {
                return response()->json([
                    'message' => 'Payment is still pending.',
                ], 200);
            }

            if ($result === 'refunded') {
                return response()->json([
                    'message' => 'Refund processed successfully.',
                ], 200);
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to process Paymob callback.',
            ], 500);
        }

        return response()->json([
            'message' => 'Callback processed successfully.',
        ], 200);
    }

    /**
     * Read Paymob credentials from database settings first,
     * with config fallback for backward compatibility.
     */
    protected function gatewaySettings(): array
    {
        $setting = PaymentGatewaySetting::query()
            ->where('gateway', 'paymob')
            ->first();

        return is_array($setting?->credentials)
            ? $setting->credentials
            : [];
    }

    protected function hmacSecret(): ?string
    {
        $settings = $this->gatewaySettings();

        $secret = $settings['hmac_secret']
            ?? config('services.paymob.hmac_secret');

        return is_string($secret) && trim($secret) !== ''
            ? trim($secret)
            : null;
    }

    protected function verifyHmac(array $data): bool
    {
        $receivedHmac = $data['hmac'] ?? null;

        if (
            !is_string($receivedHmac) ||
            trim($receivedHmac) === ''
        ) {
            return false;
        }

        $secret = $this->hmacSecret();

        if (!$secret) {
            return false;
        }

        $transaction = $data['obj'] ?? [];

        if (!is_array($transaction) || empty($transaction)) {
            return false;
        }

        // Paymob HMAC fields and order. Do not change the order.
        $values = [
            data_get($transaction, 'amount_cents'),
            data_get($transaction, 'created_at'),
            data_get($transaction, 'currency'),
            data_get($transaction, 'error_occured'),
            data_get($transaction, 'has_parent_transaction'),
            data_get($transaction, 'id'),
            data_get($transaction, 'integration_id'),
            data_get($transaction, 'is_3d_secure'),
            data_get($transaction, 'is_auth'),
            data_get($transaction, 'is_capture'),
            data_get($transaction, 'is_refunded'),
            data_get($transaction, 'is_standalone_payment'),
            data_get($transaction, 'is_voided'),
            data_get($transaction, 'order.id'),
            data_get($transaction, 'owner'),
            data_get($transaction, 'pending'),
            data_get($transaction, 'source_data.pan'),
            data_get($transaction, 'source_data.sub_type'),
            data_get($transaction, 'source_data.type'),
            data_get($transaction, 'success'),
        ];

        $hmacString = implode(
            '',
            array_map(
                fn ($value) => $this->hmacValue($value),
                $values
            )
        );

        $calculatedHmac = hash_hmac(
            'sha512',
            $hmacString,
            $secret
        );

        return hash_equals(
            strtolower($calculatedHmac),
            strtolower(trim($receivedHmac))
        );
    }

    protected function hmacValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '';
        }

        return (string) $value;
    }
}