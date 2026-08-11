<?php

namespace App\Services\Admin;

use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 🗺️ إدارة الجغرافيا للوحة التحكم — ثلاثة مستويات:
 *
 *   بلدية (Municipality) ← محلة (SubMunicipality) ← منطقة (Zone)
 *   مثال: طرابلس المركز ← النوفليين ← بن عاشور
 *
 * كل مستوى يتبع المستوى الأعلى منه إجبارياً، والحذف يتسلسل تلقائياً للأسفل
 * عبر قيود قاعدة البيانات، لذلك نحمي الحذف بفحص الارتباطات أولاً.
 */
class GeographyService
{
    // =====================================================================
    // 🏛️ البلديات
    // =====================================================================

    public function getAllMunicipalities(?string $search = null, bool $withChildren = false)
    {
        return Municipality::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($withChildren, fn ($q) => $q->with('subMunicipalities.zones'))
            ->withCount(['subMunicipalities', 'zones'])
            ->orderBy('name')
            ->get();
    }

    public function createMunicipality(array $data): Municipality
    {
        return Municipality::create(['name' => trim($data['name'])]);
    }

    public function updateMunicipality(Municipality $municipality, array $data): Municipality
    {
        $municipality->update(['name' => trim($data['name'])]);

        return $municipality->refresh();
    }

    /**
     * حذف بلدية — يتسلسل على محلاتها ومناطقها، لذا نرفض إن كانت أي منطقة مستخدمة.
     */
    public function deleteMunicipality(Municipality $municipality): void
    {
        $zoneIds = $municipality->zones()->pluck('zones.id')->all();
        $this->assertZonesAreFree($zoneIds, 'لا يمكن حذف هذه البلدية لأن مناطقها مستخدمة حالياً');

        DB::transaction(fn () => $municipality->delete());
    }

    // =====================================================================
    // 🏘️ المحلات
    // =====================================================================

    public function getSubMunicipalities(Municipality $municipality, ?string $search = null)
    {
        return $municipality->subMunicipalities()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->withCount('zones')
            ->orderBy('name')
            ->get();
    }

    public function createSubMunicipality(Municipality $municipality, array $data): SubMunicipality
    {
        $name = trim($data['name']);
        $this->assertSubMunicipalityNameIsFree($municipality, $name);

        return SubMunicipality::create([
            'municipality_id' => $municipality->id,
            'name'            => $name,
        ]);
    }

    public function updateSubMunicipality(SubMunicipality $subMunicipality, array $data): SubMunicipality
    {
        $name = trim($data['name']);

        if ($subMunicipality->municipality) {
            $this->assertSubMunicipalityNameIsFree(
                $subMunicipality->municipality,
                $name,
                $subMunicipality->id
            );
        }

        $subMunicipality->update(['name' => $name]);

        return $subMunicipality->refresh();
    }

    /**
     * حذف محلة — يتسلسل على مناطقها، لذا نرفض إن كانت أي منطقة مستخدمة.
     */
    public function deleteSubMunicipality(SubMunicipality $subMunicipality): void
    {
        $zoneIds = $subMunicipality->zones()->pluck('id')->all();
        $this->assertZonesAreFree($zoneIds, 'لا يمكن حذف هذه المحلة لأن مناطقها مستخدمة حالياً');

        DB::transaction(fn () => $subMunicipality->delete());
    }

    // =====================================================================
    // 📍 المناطق
    // =====================================================================

    public function getZonesOfSubMunicipality(SubMunicipality $subMunicipality, ?string $search = null)
    {
        return $subMunicipality->zones()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();
    }

    /**
     * كل مناطق البلدية عبر كل محلاتها (عرض مسطّح مريح للواجهة)
     */
    public function getZonesOfMunicipality(Municipality $municipality, ?string $search = null)
    {
        return $municipality->zones()
            ->when($search, fn ($q) => $q->where('zones.name', 'like', "%{$search}%"))
            ->orderBy('zones.name')
            ->get();
    }

    public function createZone(SubMunicipality $subMunicipality, array $data): Zone
    {
        $name = trim($data['name']);
        $this->assertZoneNameIsFree($subMunicipality, $name);

        return Zone::create([
            'name'                => $name,
            'sub_municipality_id' => $subMunicipality->id,
        ]);
    }

    public function updateZone(Zone $zone, array $data): Zone
    {
        $name = trim($data['name']);

        if ($zone->subMunicipality) {
            $this->assertZoneNameIsFree($zone->subMunicipality, $name, $zone->id);
        }

        $zone->update(['name' => $name]);

        return $zone->refresh();
    }

    public function deleteZone(Zone $zone): void
    {
        $this->assertZonesAreFree([$zone->id], 'لا يمكن حذف هذه المنطقة لأنها مستخدمة حالياً');

        $zone->delete();
    }

    // =====================================================================
    // 🔒 حمايات ومساعدات
    // =====================================================================

    private function assertSubMunicipalityNameIsFree(
        Municipality $municipality,
        string $name,
        ?int $ignoreId = null
    ): void {
        $duplicate = $municipality->subMunicipalities()
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            throw new Exception("توجد محلة بنفس الاسم في بلدية {$municipality->name} بالفعل.");
        }
    }

    private function assertZoneNameIsFree(
        SubMunicipality $subMunicipality,
        string $name,
        ?int $ignoreId = null
    ): void {
        $duplicate = $subMunicipality->zones()
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            throw new Exception("توجد منطقة بنفس الاسم في محلة {$subMunicipality->name} بالفعل.");
        }
    }

    /**
     * يرفض العملية إذا كانت أي من المناطق مرتبطة بسائق أو مدرسة أو عنوان
     *
     * @param  array<int>  $zoneIds
     */
    private function assertZonesAreFree(array $zoneIds, string $prefix): void
    {
        if (empty($zoneIds)) {
            return;
        }

        $usage = [
            'drivers'   => DB::table('driver_zone')->whereIn('zone_id', $zoneIds)->count(),
            'schools'   => DB::table('schools')->whereIn('zone_id', $zoneIds)->count(),
            'addresses' => DB::table('addresses')->whereIn('zone_id', $zoneIds)->count(),
        ];

        if (array_sum($usage) === 0) {
            return;
        }

        $parts = [];
        if ($usage['drivers'] > 0)   $parts[] = "{$usage['drivers']} سائق";
        if ($usage['schools'] > 0)   $parts[] = "{$usage['schools']} مدرسة";
        if ($usage['addresses'] > 0) $parts[] = "{$usage['addresses']} عنوان";

        throw new Exception($prefix . ' (' . implode(' و ', $parts) . '). يرجى نقلها أو حذفها أولاً.');
    }

    /**
     * إلحاق إحصاءات الاستخدام بكل منطقة حتى تعطّل الواجهة زر الحذف مسبقاً
     */
    public function attachUsageCounts($zones): void
    {
        if ($zones->isEmpty()) {
            return;
        }

        $ids = $zones->pluck('id')->all();

        $drivers   = DB::table('driver_zone')->whereIn('zone_id', $ids)
            ->selectRaw('zone_id, COUNT(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');
        $schools   = DB::table('schools')->whereIn('zone_id', $ids)
            ->selectRaw('zone_id, COUNT(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');
        $addresses = DB::table('addresses')->whereIn('zone_id', $ids)
            ->selectRaw('zone_id, COUNT(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');

        foreach ($zones as $zone) {
            $zone->drivers_count   = (int) ($drivers[$zone->id] ?? 0);
            $zone->schools_count   = (int) ($schools[$zone->id] ?? 0);
            $zone->addresses_count = (int) ($addresses[$zone->id] ?? 0);
            $zone->can_delete      = ($zone->drivers_count + $zone->schools_count + $zone->addresses_count) === 0;
        }
    }
}
