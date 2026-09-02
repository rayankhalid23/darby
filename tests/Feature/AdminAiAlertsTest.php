<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAlert;
use App\Models\Driver\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiAlertsTest extends TestCase
{
    /**
     * اختبار استرجاع التفاصيل الكاملة للتنبيه بصيغة 360 Breakdown JSON
     */
    public function test_admin_can_retrieve_360_degree_ai_alert_breakdown(): void
    {
        $adminUser = User::factory()->create(['role_id' => 1]);
        $driverUser = User::factory()->create([
            'full_name' => 'أحمد علي',
        ]);

        $driver = Driver::create([
            'user_id'       => $driverUser->id,
            'status'        => 'Suspended',
            'is_searchable' => false,
            'rating_avg'    => 3.8,
        ]);

        $alert = AdminAlert::create([
            'driver_id'         => $driver->id,
            'risk_level'        => 'CRITICAL',
            'actions_taken'     => ['BLOCK_FROM_SEARCH', 'SEND_ADMIN_ALERT', 'ADJUST_RATING'],
            'admin_message'     => 'CRITICAL_SAFETY_VIOLATION: Single major safety breach detected.',
            'reasoning'         => 'CRITICAL_SAFETY_VIOLATION: Single major safety breach detected.',
            'ai_metrics'        => [
                'total_reviews_analyzed'         => 4,
                'unique_parents_count'           => 3,
                'operational_strikes_count'      => 3,
                'ignored_external_factors_count' => 1,
            ],
            'evaluated_reviews' => [
                [
                    'parent_id' => 'P_102',
                    'text'      => 'السائق يقود بسرعة عالية وتلفظ بعبارات غير لائقة',
                    'date'      => '2026-08-28',
                ],
            ],
            'metadata'          => [
                'rating_change' => -1.0,
            ],
            'is_resolved'       => false,
            'alert_type'        => 'suspend_driver',
            'title'             => '⛔ إيقاف تلقائي لسائق بقرار الذكاء الاصطناعي',
            'message'           => 'CRITICAL_SAFETY_VIOLATION',
            'severity'          => 3,
        ]);

        $response = $this->actingAs($adminUser)->getJson("/api/v1/admin/ai-alerts/{$alert->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'alert_id'    => $alert->id,
                    'is_resolved' => false,
                    'driver'      => [
                        'id'            => $driver->id,
                        'name'          => 'أحمد علي',
                        'phone'         => $driverUser->phone_number,
                        'current_rating' => 3.8,
                        'is_searchable' => false,
                        'status'        => 'Suspended',
                    ],
                    'ai_decision' => [
                        'risk_level'    => 'CRITICAL',
                        'actions_taken' => ['BLOCK_FROM_SEARCH', 'SEND_ADMIN_ALERT', 'ADJUST_RATING'],
                        'rating_change' => -1.0,
                    ],
                    'metrics'     => [
                        'total_reviews_analyzed'         => 4,
                        'unique_parents_count'           => 3,
                        'operational_strikes_count'      => 3,
                        'ignored_external_factors_count' => 1,
                    ],
                ],
            ]);
    }

    /**
     * اختبار تسوية وتأكيد حل التنبيه (Resolve Alert)
     */
    public function test_admin_can_resolve_ai_alert(): void
    {
        $adminUser = User::factory()->create(['role_id' => 1]);
        $driverUser = User::factory()->create();

        $driver = Driver::create([
            'user_id'       => $driverUser->id,
            'status'        => 'Approved',
            'is_searchable' => true,
        ]);

        $alert = AdminAlert::create([
            'driver_id'   => $driver->id,
            'risk_level'  => 'HIGH',
            'is_resolved' => false,
            'title'       => 'تنبيه للتجربة',
            'message'     => 'رسالة التنبيه',
        ]);

        $response = $this->actingAs($adminUser)->postJson("/api/v1/admin/ai-alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'alert_id'    => $alert->id,
                    'is_resolved' => true,
                ],
            ]);

        $this->assertTrue((bool) $alert->fresh()->is_resolved);
    }

    /**
     * اختبار إلغاء حظر وإعادة تفعيل السائق يدوياً (Unblock Driver)
     */
    public function test_admin_can_unblock_driver(): void
    {
        $adminUser = User::factory()->create(['role_id' => 1]);
        $driverUser = User::factory()->create(['is_active' => false]);

        $driver = Driver::create([
            'user_id'       => $driverUser->id,
            'status'        => 'Suspended',
            'is_searchable' => false,
        ]);

        $alert = AdminAlert::create([
            'driver_id'   => $driver->id,
            'risk_level'  => 'CRITICAL',
            'is_resolved' => false,
            'title'       => 'تنبيه إيقاف',
            'message'     => 'إيقاف للتجربة',
        ]);

        $response = $this->actingAs($adminUser)->postJson("/api/v1/admin/drivers/{$driver->id}/unblock");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'driver_id'     => $driver->id,
                    'status'        => 'Approved',
                    'is_searchable' => true,
                ],
            ]);

        $driver->refresh();
        $this->assertEquals('Approved', $driver->status);
        $this->assertTrue((bool) $driver->is_searchable);
        $this->assertTrue((bool) $driver->user->is_active);
        $this->assertTrue((bool) $alert->fresh()->is_resolved);
    }
}
