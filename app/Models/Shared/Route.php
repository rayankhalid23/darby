<?php

namespace App\Models\Shared;

use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $table = 'routes';

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'subscription_request_id',
        'route_name',
        'route_type',
        'shift_slot',
        'start_time',
        'optimized_points',
        'total_distance',
        'estimated_duration',
        'status',
    ];

    protected $casts = [
        'optimized_points' => 'array',
        'total_distance' => 'decimal:2',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'route_id');
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(ActiveSubscription::class, 'route_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence_order');
    }

    /**
     * توليد اسم مسار عام مبني على الفترة والاتجاه بدون أسماء أشخاص
     */
    public static function generateGenericRouteName(?string $timing = null, ?string $direction = 'both', ?string $routeType = null): string
    {
        $t = strtoupper($timing ?? '');
        if (empty($t) && !empty($routeType)) {
            $t = (strtolower($routeType) === 'afternoon' || strtolower($routeType) === 'evening') ? 'EVENING' : 'MORNING';
        }

        $timingLabel = in_array($t, ['EVENING', 'AFTERNOON']) ? 'مسائي' : 'صباحي';
        
        $d = strtolower($direction ?? 'both');
        $dirLabel = match($d) {
            'go', 'pickup', 'to_school', 'one_way_to', 'morning_go', 'afternoon_go' => 'ذهاب',
            'return', 'dropoff', 'from_school', 'one_way_from', 'morning_return', 'afternoon_return' => 'عودة',
            default => 'ذهاب وعودة',
        };

        return "مسار {$timingLabel} - {$dirLabel}";
    }

    /**
     * الخاصية المحسوبة لاسم المسار بحيث ترجع دائماً اسماً عاماً نظيفاً
     */
    public function getFormattedRouteNameAttribute(): string
    {
        if (!empty($this->route_name)) {
            $name = trim($this->route_name);
            // إذا كان الاسم عاماً بالفعل ولا يحتوي على أسماء أشخاص
            if (preg_match('/^مسار (صباحي|مسائي) - (ذهاب|عودة|إياب|ذهاب وعودة)$/u', $name)) {
                return $name;
            }
        }
        return self::generateGenericRouteName($this->shift_slot ?? null, $this->subscriptionRequest?->direction ?? 'both', $this->route_type);
    }
}
