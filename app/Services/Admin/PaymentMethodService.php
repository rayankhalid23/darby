<?php

namespace App\Services\Admin;

use App\Models\Shared\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentMethodService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentMethod::latest('sort_order')->latest('id');

        if (!empty($filters['target_audience']) && $filters['target_audience'] !== 'all') {
            $query->where(function ($q) use ($filters) {
                $q->where('target_audience', $filters['target_audience'])
                  ->orWhere('target_audience', 'both');
            });
        }

        if (!empty($filters['processing_type'])) {
            $query->where('processing_type', $filters['processing_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', $search)
                  ->orWhere('name_en', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('account_name', 'like', $search)
                  ->orWhere('account_number', 'like', $search);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        return $query->paginate($perPage);
    }

    public function getActiveFor(string $audience = 'both'): Collection
    {
        return PaymentMethod::active()
            ->where(function ($q) use ($audience) {
                if ($audience === 'parent') {
                    $q->whereIn('target_audience', ['parent', 'both']);
                } elseif ($audience === 'driver') {
                    $q->whereIn('target_audience', ['driver', 'both']);
                }
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function getById(int $id): PaymentMethod
    {
        return PaymentMethod::findOrFail($id);
    }

    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    public function update(int $id, array $data): PaymentMethod
    {
        $method = PaymentMethod::findOrFail($id);
        $method->update($data);
        return $method->fresh();
    }

    public function toggleStatus(int $id): PaymentMethod
    {
        $method = PaymentMethod::findOrFail($id);
        $method->is_active = !$method->is_active;
        $method->save();
        return $method;
    }

    public function delete(int $id): bool
    {
        $method = PaymentMethod::findOrFail($id);
        return (bool) $method->delete();
    }
}
