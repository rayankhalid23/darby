<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تخزين «لقطة» (Snapshot) لاسم وإحداثيات منزل الطفل ومدرسته داخل request_children
     * لحظة إنشاء طلب الاشتراك.
     *
     * السبب: هذه الأعمدة كانت موجودة ثم حُذفت في مهاجرة 2026_08_26_120748، بينما بقي
     * الكود في SubscriptionRequestService::createActiveSubscriptions() يقرأ
     * $child->pivot->home_lat / school_lat فيحصل دائماً على null. كما أن قراءة الموقع
     * من علاقة الطفل الحية تعني أن تغيير ولي الأمر لعنوانه أو مدرسته لاحقاً يُعيد كتابة
     * تاريخ الطلبات القديمة ويجعل السعر والمسافة المحفوظين غير مطابقين للعنوان المعروض.
     *
     * القيم تُملأ تلقائياً من بيانات الطفل (children.address_id / children.school_id)
     * وقت الإنشاء، ولا تُحذف أي بيانات قائمة.
     */
    public function up(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            if (!Schema::hasColumn('request_children', 'home_label')) {
                $table->string('home_label')->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('request_children', 'home_lat')) {
                $table->decimal('home_lat', 10, 8)->nullable()->after('home_label');
            }
            if (!Schema::hasColumn('request_children', 'home_lng')) {
                $table->decimal('home_lng', 11, 8)->nullable()->after('home_lat');
            }
            if (!Schema::hasColumn('request_children', 'school_label')) {
                $table->string('school_label')->nullable()->after('home_lng');
            }
            if (!Schema::hasColumn('request_children', 'school_lat')) {
                $table->decimal('school_lat', 10, 8)->nullable()->after('school_label');
            }
            if (!Schema::hasColumn('request_children', 'school_lng')) {
                $table->decimal('school_lng', 11, 8)->nullable()->after('school_lat');
            }
        });

        $this->backfillFromChildren();
    }

    /**
     * تعبئة السجلات القائمة من أحدث بيانات الطفل المتاحة (أفضل تقريب ممكن للسجلات
     * التي أُنشئت قبل وجود هذه الأعمدة). لا تُلمس أي قيمة غير فارغة.
     */
    private function backfillFromChildren(): void
    {
        DB::table('request_children')
            // التجميع داخل closure ضروري: chunkById يضيف شرط `id > ?` وبدون التجميع
            // يبتلع الـ OR الشرط الأخير فتُعاد نفس الدفعة إلى ما لا نهاية.
            ->where(function ($query) {
                $query->whereNull('home_lat')
                      ->orWhereNull('home_lng')
                      ->orWhereNull('home_label')
                      ->orWhereNull('school_lat')
                      ->orWhereNull('school_lng')
                      ->orWhereNull('school_label');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $childIds = $rows->pluck('child_id')->unique()->all();

                $children = DB::table('children')
                    ->leftJoin('addresses', function ($join) {
                        $join->on('addresses.id', '=', 'children.address_id')
                             ->whereNull('addresses.deleted_at');
                    })
                    ->leftJoin('schools', 'schools.id', '=', 'children.school_id')
                    ->whereIn('children.id', $childIds)
                    ->get([
                        'children.id as child_id',
                        'addresses.label as home_label',
                        'addresses.lat as home_lat',
                        'addresses.lng as home_lng',
                        'schools.name as school_label',
                        'schools.lat as school_lat',
                        'schools.lng as school_lng',
                    ])
                    ->keyBy('child_id');

                foreach ($rows as $row) {
                    $source = $children->get($row->child_id);
                    if (!$source) {
                        continue;
                    }

                    // لا تُدهس أي قيمة محفوظة مسبقاً — تُملأ الفراغات فقط
                    $updates = [];
                    foreach (['home_label', 'home_lat', 'home_lng', 'school_label', 'school_lat', 'school_lng'] as $column) {
                        if (($row->{$column} ?? null) === null && ($source->{$column} ?? null) !== null) {
                            $updates[$column] = $source->{$column};
                        }
                    }

                    if (!empty($updates)) {
                        DB::table('request_children')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['home_label', 'home_lat', 'home_lng', 'school_label', 'school_lat', 'school_lng'],
                fn ($column) => Schema::hasColumn('request_children', $column)
            ));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
