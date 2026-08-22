<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * تشغيل سيدر الأدوار (Roles Table)
     * مبني بدقة بناءً على منطق إنشاء وتسجيل: مدير النظام (1)، المشرف (2)، ولي الأمر (3)، والسائق (4)
     */
    public function run(): void
    {
        $roles = [
            [
                'id'           => 1,
                'name'         => 'admin',
                'display_name' => 'مدير النظام',
                'description'  => 'صلاحيات كاملة وغير مقيدة لإدارة المنصة، السائقين، المشرفين، والعمليات المالية والتقارير.',
                'permissions'  => json_encode([
                    'all' => true,
                    'dashboard' => ['view', 'stats', 'active_trips'],
                    'admins' => ['view', 'create', 'update', 'delete'],
                    'supervisors' => ['view', 'create', 'update', 'delete'],
                    'drivers' => ['view', 'approve', 'reject', 'update', 'delete', 'review_changes'],
                    'parents' => ['view', 'update', 'delete'],
                    'children' => ['view', 'update'],
                    'complaints' => ['view', 'review', 'resolve'],
                    'financial' => ['view_summary', 'manage_wallets', 'recharges', 'withdrawals', 'disputes', 'settle_contracts', 'cancellations'],
                    'reports' => ['kpi', 'financial', 'trips', 'subscriptions', 'drivers_performance', 'export'],
                    'geography' => ['municipalities', 'sub_municipalities', 'zones'],
                    'schools' => ['view', 'create', 'update', 'delete'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'           => 2,
                'name'         => 'supervisor',
                'display_name' => 'مشرف',
                'description'  => 'صلاحيات إشرافية لمراجعة طلبات السائقين، مراقبة الرحلات الحية، حل الشكاوى، وإدارة المدارس.',
                'permissions'  => json_encode([
                    'dashboard' => ['view', 'stats', 'active_trips'],
                    'drivers' => ['view', 'review', 'pending_changes', 'update'],
                    'schools' => ['view', 'create', 'update', 'delete'],
                    'zones' => ['view', 'create', 'update'],
                    'complaints' => ['view', 'review'],
                    'driver_reviews' => ['view', 'delete'],
                    'trips' => ['view', 'monitor_live'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'           => 3,
                'name'         => 'parent',
                'display_name' => 'ولي أمر',
                'description'  => 'حساب ولي الأمر لإضافة الأبناء، إدارة عناوين التوصيل، البحث عن السائقين، طلب الاشتراكات وتتبع الرحلات المباشرة.',
                'permissions'  => json_encode([
                    'profile' => ['view', 'update', 'change_email'],
                    'children' => ['view', 'create', 'update', 'delete', 'set_absence', 'confirm_pickup'],
                    'addresses' => ['view', 'create', 'update', 'delete'],
                    'drivers' => ['search', 'view_profile', 'review'],
                    'subscriptions' => ['request', 'view_active', 'cancel'],
                    'contracts' => ['view', 'accept', 'reject', 'pdf'],
                    'trips' => ['view_active', 'track_live', 'timeline', 'history', 'dispute'],
                    'wallet' => ['view_balance', 'recharge', 'hold_trip'],
                    'complaints' => ['create', 'view', 'update', 'delete'],
                    'chat' => ['view', 'send'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'           => 4,
                'name'         => 'driver',
                'display_name' => 'سائق',
                'description'  => 'حساب السائق لإدارة المركبة والوثائق، تحديد نطاق التغطية والمقاعد، واستقبال طلبات التوصيل وتنفيذ الرحلات اليومية.',
                'permissions'  => json_encode([
                    'profile' => ['view', 'update', 'complete_profile', 'update_vehicle', 'update_legal_data'],
                    'preferences' => ['view', 'update', 'manage_zones'],
                    'addresses' => ['view', 'create', 'update', 'delete'],
                    'subscriptions' => ['view_requests', 'accept', 'reject', 'feasibility_check', 'view_active', 'cancel'],
                    'routes' => ['view', 'create', 'update', 'delete', 'reorder_stops', 'assign_subscription'],
                    'trips' => ['start', 'live_tracking', 'update_location', 'pickup', 'absent', 'dropoff', 'verify_qr', 'report_breakdown', 'complete'],
                    'wallet' => ['view_balance', 'withdraw'],
                    'chat' => ['view', 'send'],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
