<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Constants\PermissionConstants;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPerms = PermissionConstants::getDefaultRolePermissions();

        $roles = [
            [
                'id'          => 1,
                'name'        => 'super_admin',
                'display_name'=> 'مدير النظام العام',
                'description' => 'صلاحيات كاملة وغير مقيدة لإدارة المنصة والإعدادات والمالية بالكامل.',
                'permissions' => json_encode($defaultPerms['super_admin'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 2,
                'name'        => 'operations_supervisor',
                'display_name'=> 'مشرف العمليات والرادار',
                'description' => 'صلاحيات مراقبة وتتبع الرحلات الحية على الرادار، تشغيل الرحلات، وإدارة المدارس والمناطق.',
                'permissions' => json_encode($defaultPerms['operations_supervisor'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 3,
                'name'        => 'parent',
                'display_name'=> 'ولي أمر',
                'description' => 'إدارة بيانات الأبناء، حجز الاشتراكات، شحن المحفظة، تتبع الرحلات الحية، وتقديم التقييمات والشكاوى.',
                'permissions' => json_encode(['children' => ['create', 'update'], 'subscriptions' => ['request', 'cancel'], 'wallet' => ['recharge', 'view'], 'trips' => ['track_live']], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 4,
                'name'        => 'driver',
                'display_name'=> 'سائق حافلة/فان',
                'description' => 'استقبال وتأكيد طلبات التوصيل، إدارة المسارات والوقفات، تشغيل الرحلات، مسح الحضور وتتبع الموقع.',
                'permissions' => json_encode(['trips' => ['start', 'complete', 'update_location', 'board_child'], 'routes' => ['manage'], 'wallet' => ['withdraw']], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 5,
                'name'        => 'fleet_supervisor',
                'display_name'=> 'مشرف شؤون السائقين والأسطول',
                'description' => 'مراجعة وتدقيق واعتماد طلبات انضمام السائقين، مراجعة تعديلات المركبات، وتجميد الحسابات.',
                'permissions' => json_encode($defaultPerms['fleet_supervisor'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 6,
                'name'        => 'support_supervisor',
                'display_name'=> 'مشرف خدمة العملاء والشكاوى',
                'description' => 'معالجة شكاوى أولياء الأمور والسائقين، متابعة تنبيهات الجودة والتقييمات، وإرسال الإشعارات.',
                'permissions' => json_encode($defaultPerms['support_supervisor'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 7,
                'name'        => 'finance_officer',
                'display_name'=> 'المشرف المالي ومسؤول الخزينة',
                'description' => 'إدارة الخزينة، تدقيق طلبات السحب والشحن، تحرير الأمانات، البت في النزاعات والتسويات المالية.',
                'permissions' => json_encode($defaultPerms['finance_officer'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id'          => 8,
                'name'        => 'geography_supervisor',
                'display_name'=> 'مشرف المدارس والبيانات الجغرافية',
                'description' => 'إدارة المدارس، البلديات، المحلات، وتخطيط المناطق الجغرافية.',
                'permissions' => json_encode($defaultPerms['geography_supervisor'], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }
    }
}
