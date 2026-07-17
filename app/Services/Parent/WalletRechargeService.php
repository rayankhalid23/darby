<?php

namespace App\Services\Parent;

use App\Models\Shared\RechargeRequest;
use App\Models\Parent\ParentModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class WalletRechargeService
{
    private const PAYMENT_METHODS = ['ncb', 'libyana', 'almadar'];

    public function initiateRecharge(int $userId, float $amount, string $paymentMethod, ?string $referenceNumber = null): RechargeRequest
    {
        if (!in_array($paymentMethod, self::PAYMENT_METHODS)) {
            throw ValidationException::withMessages([
                'payment_method' => ['طريقة الدفع غير مدعومة. الطرق المتاحة: المصرف التجاري، ليبيانا، المدار.'],
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['الحد الأدنى للشحن هو 1 دينار ليبي.'],
            ]);
        }

        if ($amount > 50000) {
            throw ValidationException::withMessages([
                'amount' => ['الحد الأقصى للشحن هو 50,000 دينار ليبي.'],
            ]);
        }

        $methodLabels = [
            'ncb'     => 'المصرف التجاري الوطني',
            'libyana' => 'ليبيانا',
            'almadar' => 'المدار',
        ];

        return RechargeRequest::create([
            'parent_id'        => $userId,
            'amount'           => $amount,
            'payment_method'   => $paymentMethod,
            'reference_number' => $referenceNumber,
            'status'           => 'pending',
            'notes'            => 'طلب شحن عبر ' . ($methodLabels[$paymentMethod] ?? $paymentMethod),
        ]);
    }

    public function completeRecharge(int $rechargeId, int $adminId): RechargeRequest
    {
        return DB::transaction(function () use ($rechargeId, $adminId) {
            $request = RechargeRequest::where('id', $rechargeId)
                ->where('status', 'pending')
                ->firstOrFail();

            $parent = ParentModel::find($request->parent_id);
            if (!$parent) {
                $parent = ParentModel::where('user_id', $request->parent_id)->firstOrFail();
            }

            $parent->deposit($request->amount * 100);

            $request->update([
                'status'       => 'completed',
                'admin_id'     => $adminId,
                'completed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function failRecharge(int $rechargeId, int $adminId, string $reason): RechargeRequest
    {
        $request = RechargeRequest::where('id', $rechargeId)
            ->where('status', 'pending')
            ->firstOrFail();

        $request->update([
            'status'   => 'failed',
            'admin_id' => $adminId,
            'notes'    => $reason,
        ]);

        return $request->fresh();
    }

    public function getPaymentMethods(): array
    {
        return [
            [
                'id'          => 'ncb',
                'name'        => 'المصرف التجاري الوطني',
                'name_en'     => 'National Commercial Bank',
                'description' => 'تحويل بنكي عبر المصرف التجاري الوطني. يرجى إدخال رقم الإحالة.',
                'requires_reference' => true,
                'instructions' => [
                    'قم بتحويل المبلغ إلى حساب الشركة لدى المصرف التجاري الوطني.',
                    'احتفظ برقم الإحالة (Reference Number) من إيصال التحويل.',
                    'أدخل رقم الإحالة في الحقل المخصص لإتمام عملية الشحن.',
                ],
            ],
            [
                'id'          => 'libyana',
                'name'        => 'ليبيانا',
                'name_en'     => 'Libyana',
                'description' => 'شحن المحفظة عبر رصيد ليبيانا.',
                'requires_reference' => false,
                'instructions' => [
                    'اختر شحن المحفظة عبر ليبيانا.',
                    'سيتم إرسال رمز تأكيد إلى هاتفك.',
                    'أدخل الرمز لتأكيد عملية الشحن.',
                ],
            ],
            [
                'id'          => 'almadar',
                'name'        => 'المدار',
                'name_en'     => 'Al-Madar',
                'description' => 'شحن المحفظة عبر رصيد المدار.',
                'requires_reference' => false,
                'instructions' => [
                    'اختر شحن المحفظة عبر المدار.',
                    'سيتم إرسال رمز تأكيد إلى هاتفك.',
                    'أدخل الرمز لتأكيد عملية الشحن.',
                ],
            ],
        ];
    }
}
