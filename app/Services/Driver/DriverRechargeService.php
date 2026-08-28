<?php

namespace App\Services\Driver;

use App\Models\Driver\DriverRechargeRequest;
use App\Models\Shared\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

class DriverRechargeService
{
    public function getActivePaymentMethods(): Collection
    {
        return PaymentMethod::active()
            ->forDrivers()
            ->orderBy('sort_order')
            ->get();
    }

    public function submitRechargeRequest(int $driverId, float $amount, ?int $paymentMethodId, ?string $referenceNumber, ?UploadedFile $proofImage = null, ?string $notes = null): DriverRechargeRequest
    {
        if ($paymentMethodId) {
            $method = PaymentMethod::find($paymentMethodId);
            if (!$method || !$method->is_active) {
                throw ValidationException::withMessages([
                    'payment_method_id' => ['طريقة الدفع المحددة غير مفعلة أو غير متوفرة حالياً.'],
                ]);
            }
            if ($amount < (float) $method->min_amount) {
                throw ValidationException::withMessages([
                    'amount' => ["الحد الأدنى للشحن عبر هذه الطريقة هو {$method->min_amount} د.ل."],
                ]);
            }
            if ($amount > (float) $method->max_amount) {
                throw ValidationException::withMessages([
                    'amount' => ["الحد الأقصى للشحن عبر هذه الطريقة هو {$method->max_amount} د.ل."],
                ]);
            }
        } else {
            if ($amount < 1) {
                throw ValidationException::withMessages([
                    'amount' => ['الحد الأدنى للشحن هو 1 دينار ليبي.'],
                ]);
            }
        }

        // فحص منع تكرار نفس رقم الحوالة لنفس وسيلة الدفع
        if ($referenceNumber) {
            $duplicate = DriverRechargeRequest::where('reference_number', $referenceNumber)
                ->where('driver_id', $driverId)
                ->whereIn('status', [DriverRechargeRequest::STATUS_PENDING, DriverRechargeRequest::STATUS_APPROVED])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference_number' => ['تم تقديم طلب شحن سابق بنفس هذا الرقم المرجعي مسبقاً.'],
                ]);
            }
        }

        $proofImagePath = null;
        if ($proofImage) {
            $proofImagePath = $proofImage->store('recharges/drivers', 'public');
        }

        return DriverRechargeRequest::create([
            'driver_id'         => $driverId,
            'payment_method_id' => $paymentMethodId,
            'amount'            => $amount,
            'proof_image_url'   => $proofImagePath ? asset('storage/' . $proofImagePath) : null,
            'reference_number'  => $referenceNumber,
            'status'            => DriverRechargeRequest::STATUS_PENDING,
            'notes'             => $notes,
        ])->load('paymentMethod');
    }

    public function getDriverRechargeHistory(int $driverId, array $filters = []): LengthAwarePaginator
    {
        $query = DriverRechargeRequest::with('paymentMethod')
            ->where('driver_id', $driverId)
            ->latest('id');

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        return $query->paginate($perPage);
    }
}
