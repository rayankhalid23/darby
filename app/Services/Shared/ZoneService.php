<?php

namespace App\Services\Shared;

use App\Models\Shared\Zone;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use Illuminate\Support\Facades\DB;
use Exception;

class ZoneService
{
    /**
     * جلب كافة المناطق مع شحن البيانات الجغرافية
     */
    public function getAllZones()
    {
        return Zone::with('subMunicipality.municipality')->orderBy('name', 'asc')->get();
    }

    /**
     * جلب تفاصيل منطقة واحدة
     */
    public function getZoneById(int $id): Zone
    {
        $zone = Zone::with('subMunicipality.municipality')->find($id);
        if (!$zone) {
            throw new Exception("المنطقة المطلوبة غير موجودة في النظام.");
        }
        return $zone;
    }

    /**
     * إضافة منطقة جديدة مع منع التكرار
     */
    public function createZone(array $data): Zone
    {
        $exists = Zone::where('name', $data['name'])->exists();
        if ($exists) {
            throw new Exception("هذه المنطقة مسجلة مسبقاً في النظام.");
        }

        $payload = ['name' => $data['name']];
        if (!empty($data['sub_municipality_id'])) {
            $payload['sub_municipality_id'] = $data['sub_municipality_id'];
        }

        $zone = Zone::create($payload);
        return $zone->load('subMunicipality.municipality');
    }

    /**
     * تعديل اسم منطقة موجودة وبلديتها الفرعية
     */
    public function updateZone(Zone $zone, array $data): Zone
    {
        $exists = Zone::where('name', $data['name'])->where('id', '!=', $zone->id)->exists();
        if ($exists) {
            throw new Exception("تعذر التعديل: هناك منطقة أخرى تحمل نفس الاسم.");
        }

        $payload = ['name' => $data['name']];
        if (array_key_exists('sub_municipality_id', $data)) {
            $payload['sub_municipality_id'] = $data['sub_municipality_id'];
        }

        $zone->update($payload);
        return $zone->load('subMunicipality.municipality');
    }

    /**
     * حذف منطقة نهائياً مع حماية علاقات السائقين النشطين
     */
    public function deleteZone(Zone $zone): void
    {
        // شبكة أمان: فحص ما إذا كان هناك سائقون مسجلون في هذه المنطقة حالياً
        $hasDrivers = DB::table('driver_zone')->where('zone_id', $zone->id)->exists();
        
        if ($hasDrivers) {
            throw new Exception("لا يمكن حذف هذه المنطقة لأنها تحتوي على سائقين نشطين حالياً، قم بنقل السائقين أولاً.");
        }

        $zone->delete();
    }

    // =========================================================================
    // 🏢 إدارة البلديات الكبرى (Municipalities)
    // =========================================================================

    public function getAllMunicipalities()
    {
        return Municipality::with('subMunicipalities.zones')->orderBy('name', 'asc')->get();
    }

    public function createMunicipality(array $data): Municipality
    {
        $exists = Municipality::where('name', $data['name'])->exists();
        if ($exists) {
            throw new Exception("هذه البلدية الكبرى مسجلة مسبقاً في النظام.");
        }
        return Municipality::create(['name' => $data['name']]);
    }

    public function updateMunicipality(Municipality $municipality, array $data): Municipality
    {
        $exists = Municipality::where('name', $data['name'])->where('id', '!=', $municipality->id)->exists();
        if ($exists) {
            throw new Exception("هناك بلدية أخرى تحمل نفس الاسم.");
        }
        $municipality->update(['name' => $data['name']]);
        return $municipality;
    }

    public function deleteMunicipality(Municipality $municipality): void
    {
        if ($municipality->subMunicipalities()->exists()) {
            throw new Exception("لا يمكن حذف البلدية الكبرى، تتبعها بلديات فرعية قائمة.");
        }
        $municipality->delete();
    }

    // =========================================================================
    // 🏘️ إدارة البلديات الفرعية / المحلات (SubMunicipalities)
    // =========================================================================

    public function getAllSubMunicipalities()
    {
        return SubMunicipality::with('municipality', 'zones')->orderBy('name', 'asc')->get();
    }

    public function createSubMunicipality(array $data): SubMunicipality
    {
        $exists = SubMunicipality::where('name', $data['name'])
            ->where('municipality_id', $data['municipality_id'] ?? null)
            ->exists();
        if ($exists) {
            throw new Exception("هذه البلدية الفرعية مسجلة مسبقاً لهذه البلدية الكبرى.");
        }
        $sub = SubMunicipality::create([
            'name'            => $data['name'],
            'municipality_id' => $data['municipality_id'] ?? null,
        ]);
        return $sub->load('municipality');
    }

    public function updateSubMunicipality(SubMunicipality $subMunicipality, array $data): SubMunicipality
    {
        $subMunicipality->update($data);
        return $subMunicipality->load('municipality');
    }

    public function deleteSubMunicipality(SubMunicipality $subMunicipality): void
    {
        if ($subMunicipality->zones()->exists()) {
            throw new Exception("لا يمكن حذف البلدية الفرعية، تتبعها مناطق قائمة.");
        }
        $subMunicipality->delete();
    }
}