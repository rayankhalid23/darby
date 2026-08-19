<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * DriverSearchTestSeeder
 * â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
 * ط¨ظٹط§ظ†ط§طھ ظˆظ‡ظ…ظٹط© ظ…طھظƒط§ظ…ظ„ط© ظ„ط§ط®طھط¨ط§ط± ط¯ط§ظ„ط© ط§ظ„ط¨ط­ط« ظˆط§ظ„ظپظ„طھط±ط© ظˆط§ظ„طھط³ط¹ظٹط±
 *
 * طھط؛ط·ظٹ ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط§ظ„طھط§ظ„ظٹط©:
 *   âœ… ط¨ط­ط« ظ†طµظٹ ط¨ط§ظ„ط§ط³ظ…
 *   âœ… ط¨ط­ط« ظ†طµظٹ ط¨ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ
 *   âœ… ظپظ„طھط±ط© ط¬ظ†ط³ ط§ظ„ط³ط§ط¦ظ‚ (ط°ظƒط± / ط£ظ†ط«ظ‰)
 *   âœ… ظپظ„طھط±ط© ظˆط¬ظˆط¯ ظ…ظƒظٹظپ (ظ†ط¹ظ… / ظ„ط§)
 *   âœ… ظپظ„طھط±ط© ط°ظƒظٹط© ط¨ط§ظ„ظ…ظ†ط·ظ‚ط© (ظ†ظپط³ ط²ظˆظ† ط§ظ„ظ…ط¯ط±ط³ط©)
 *   âœ… ظپظ„طھط±ط© ط°ظƒظٹط© ط¨ط§ظ„ط¨ظ„ط¯ظٹط© (fallback)
 *   âœ… طھط³ط¹ظٹط± ط§ط´طھط±ط§ظƒ ط´ظ‡ط±ظٹ ظ…ط¹ ط³ظٹط§ط±ط© ظ…ظƒظٹظپط©
 *   âœ… طھط³ط¹ظٹط± ط§ط´طھط±ط§ظƒ ط´ظ‡ط±ظٹ ظ…ط¹ ط³ظٹط§ط±ط© ط؛ظٹط± ظ…ظƒظٹظپط©
 *   âœ… طھط³ط¹ظٹط± ط§ط´طھط±ط§ظƒ ظٹظˆظ…ظٹ
 *   âœ… ط·ظپظ„ ط¨ط¯ظˆظ† ط¨ظٹط§ظ†ط§طھ ظ„ظˆط¬ط³طھظٹط© (edge case)
 *   âœ… ط£ظƒط«ط± ظ…ظ† ط·ظپظ„ ظ…ط¹ط§ظ‹
 *
 * â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
 * ط¨ظٹط§ظ†ط§طھ ط§ظ„ط¯ط®ظˆظ„ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ظ„ظ„ط§ط®طھط¨ط§ط±:
 *   email:    parent.test@derbi.ly
 *   password: 12345678
 * â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
 */
class DriverSearchTestSeeder extends Seeder
{
    // ظƒظ„ظ…ط© ظ…ط±ظˆط± ظ…ظˆط­ط¯ط© ظ„ظƒظ„ ط§ظ„ط­ط³ط§ط¨ط§طھ
    private string $password;

    // â”€â”€ ظ…ط¹ط±ظپط§طھ ط§ظ„ظ€ Zones (طھظڈظ…ظ„ط£ ظ…ظ† ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ) â”€â”€
    private int $zoneIdBenAshour;     // ط¨ظ† ط¹ط§ط´ظˆط±  â†’ sub_muni: ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©
    private int $zoneIdDahra;         // ط§ظ„ط¸ظ‡ط±ط©    â†’ sub_muni: ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©
    private int $zoneIdArada;         // ط¹ط±ط§ط¯ط©     â†’ sub_muni: ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط© ط§ظ„ظ…ط±ظƒط²
    private int $zoneIdAndalus;       // ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³â†’ sub_muni: ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ ط§ظ„ظ…ط±ظƒط²
    private int $subMuniTripoliId;    // ط¨ظ„ط¯ظٹط©: ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©
    private int $subMuniSouqId;       // ط¨ظ„ط¯ظٹط©: ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط© ط§ظ„ظ…ط±ظƒط²

    // â”€â”€ ظ…ط¹ط±ظپط§طھ ط§ظ„ظ…ط¯ط§ط±ط³ â”€â”€
    private int $schoolTripoliId;     // ظ…ط¯ط±ط³ط© ظپظٹ ط²ظˆظ† ط²ط§ظˆظٹط© ط§ظ„ط¯ظ‡ظ…ط§ظ†ظٹ
    private int $schoolSouqId;        // ظ…ط¯ط±ط³ط© ظپظٹ ط²ظˆظ† ط´ط±ظپط© ط§ظ„ظ…ظ„ط§ط­ط©

    // â”€â”€ role IDs â”€â”€
    private int $roleParent = 3;
    private int $roleDriver = 4;

    public function run(): void
    {
        $this->password = Hash::make('12345678');

        $this->command->info('ًںŒچ ط¬ظ„ط¨ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ظ†ط§ط·ظ‚ ط§ظ„ط¬ط؛ط±ط§ظپظٹط©...');
        $this->resolveGeography();

        $this->command->info('ًںڈ« ط¬ظ„ط¨ / ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط¯ط§ط±ط³...');
        $this->resolveSchools();

        $this->command->info('ًں‘¨â€چًں‘©â€چًں‘§ ط¥ظ†ط´ط§ط، ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ظˆط£ط·ظپط§ظ„ظ‡ظ…...');
        $parentId = $this->createParent();

        $this->command->info('ًںڑ— ط¥ظ†ط´ط§ط، ط§ظ„ط³ط§ط¦ظ‚ظٹظ† ط§ظ„ظ…طھظ†ظˆط¹ظٹظ†...');
        $this->createDrivers();

        $this->command->info('ًں‘¶ ط¥ظ†ط´ط§ط، ط§ظ„ط£ط·ظپط§ظ„ ظ…ط¹ ط¨ظٹط§ظ†ط§طھظ‡ظ… ط§ظ„ظ„ظˆط¬ط³طھظٹط©...');
        $this->createChildren($parentId);

        $this->command->info('âœ… ط§ظƒطھظ…ظ„ ط§ظ„ظ€ Seeder ط¨ظ†ط¬ط§ط­!');
        $this->printSummary($parentId);
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // [1] ط¬ظ„ط¨ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ظ†ط§ط·ظ‚ ط§ظ„ط¬ط؛ط±ط§ظپظٹط©
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function resolveGeography(): void
    {
        // ط¬ظ„ط¨ sub_municipalities
        $subTrip = DB::table('sub_municipalities')->where('name', 'ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©')->first();
        $subSouq = DB::table('sub_municipalities')->where('name', 'ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط© ط§ظ„ظ…ط±ظƒط²')->first();
        $subAndl = DB::table('sub_municipalities')->where('name', 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ ط§ظ„ظ…ط±ظƒط²')->first();

        if (!$subTrip || !$subSouq) {
            $this->command->error('âڑ ï¸ڈ  ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط¬ط؛ط±ط§ظپظٹط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©. ط´ط؛ظ‘ظ„ ط£ظˆظ„ط§ظ‹: TripoliGeographySeeder');
            exit;
        }

        $this->subMuniTripoliId = $subTrip->id;
        $this->subMuniSouqId   = $subSouq->id;

        // ط¬ظ„ط¨ zones
        $zBen     = DB::table('zones')->where('name', 'ط¨ظ† ط¹ط§ط´ظˆط±')->first();
        $zDahra   = DB::table('zones')->where('name', 'ط§ظ„ط¸ظ‡ط±ط©')->first();
        $zArada   = DB::table('zones')->where('name', 'ط¹ط±ط§ط¯ط©')->first();
        $zAndalus = DB::table('zones')->where('name', 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³')->first();

        $this->zoneIdBenAshour = $zBen?->id   ?? 1;
        $this->zoneIdDahra     = $zDahra?->id  ?? 2;
        $this->zoneIdArada     = $zArada?->id  ?? 5;
        $this->zoneIdAndalus   = $zAndalus?->id ?? 7;
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // [2] ط¬ظ„ط¨ ط£ظˆ ط¥ظ†ط´ط§ط، ظ…ط¯ط§ط±ط³ ظ„ظ„ط§ط®طھط¨ط§ط±
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function resolveSchools(): void
    {
        // ط§ظ„ظ…ط¯ط±ط³ط© 1 ظپظٹ ط²ظˆظ† "ط²ط§ظˆظٹط© ط§ظ„ط¯ظ‡ظ…ط§ظ†ظٹ" (ط¶ظ…ظ† sub_muni: ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©)
        $zDehm = DB::table('zones')->where('name', 'ط²ط§ظˆظٹط© ط§ظ„ط¯ظ‡ظ…ط§ظ†ظٹ')->first();
        $s1 = DB::table('schools')->where('name', 'ظ…ط¯ط±ط³ط© ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط±ظƒط²ظٹط©')->first();
        if (!$s1) {
            $this->schoolTripoliId = DB::table('schools')->insertGetId([
                'name'    => 'ظ…ط¯ط±ط³ط© ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط±ظƒط²ظٹط©',
                'zone_id' => $zDehm?->id,
                'lat'     => 32.8872,
                'lng'     => 13.1913,
                'address' => 'ط²ط§ظˆظٹط© ط§ظ„ط¯ظ‡ظ…ط§ظ†ظٹطŒ ط·ط±ط§ط¨ظ„ط³',
                'status'  => 'approved',
            ]);
        } else {
            $this->schoolTripoliId = $s1->id;
        }

        // ط§ظ„ظ…ط¯ط±ط³ط© 2 ظپظٹ ط²ظˆظ† "ط´ط±ظپط© ط§ظ„ظ…ظ„ط§ط­ط©" (ط¶ظ…ظ† sub_muni: ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط© ط§ظ„ظ…ط±ظƒط²)
        $zSharaf = DB::table('zones')->where('name', 'ط´ط±ظپط© ط§ظ„ظ…ظ„ط§ط­ط©')->first();
        $s2 = DB::table('schools')->where('name', 'ظ…ط¯ط±ط³ط© ط­ط·ظٹظ†')->first();
        if (!$s2) {
            $this->schoolSouqId = DB::table('schools')->insertGetId([
                'name'    => 'ظ…ط¯ط±ط³ط© ط­ط·ظٹظ†',
                'zone_id' => $zSharaf?->id,
                'lat'     => 32.8721,
                'lng'     => 13.2380,
                'address' => 'ط´ط±ظپط© ط§ظ„ظ…ظ„ط§ط­ط©طŒ ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط©',
                'status'  => 'approved',
            ]);
        } else {
            $this->schoolSouqId = $s2->id;
        }
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // [3] ط¥ظ†ط´ط§ط، ظˆظ„ظٹ ط§ظ„ط£ظ…ط± + ط³ط¬ظ„ parents + ط¹ظ†ط§ظˆظٹظ†
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function createParent(): int
    {
        // ط§ظ„ظ…ط³طھط®ط¯ظ… ط§ظ„ط±ط¦ظٹط³ظٹ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
        $userId = DB::table('users')->insertGetId([
            'full_name'     => 'ظ…ط­ظ…ط¯ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
            'email'         => 'parent.test@derbi.ly',
            'phone_number'  => '0913334455',
            'password_hash' => $this->password,
            'role_id'       => $this->roleParent,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // ط³ط¬ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ظپظٹ ط¬ط¯ظˆظ„ parents
        DB::table('parents')->insertGetId([
            'user_id'    => $userId,
            'is_trusted' => 1,
        ]);

        // â”€â”€ ط¹ظ†ظˆط§ظ† ظ…ظ†ط²ظ„ ط§ظ„ط·ظپظ„ ط§ظ„ط£ظˆظ„ (ط¨ظ† ط¹ط§ط´ظˆط± â€“ ظ‚ط±ظٹط¨ ظ…ظ† ظ…ط¯ط±ط³ط© ط·ط±ط§ط¨ظ„ط³) â”€â”€
        // ط¥ط­ط¯ط§ط«ظٹط§طھ: ط¨ظ† ط¹ط§ط´ظˆط±طŒ ط·ط±ط§ط¨ظ„ط³ (32.9014, 13.2000)
        DB::table('addresses')->insert([
            'parent_id'  => $userId,
            'label'      => 'ظ…ظ†ط²ظ„ ط¨ظ† ط¹ط§ط´ظˆط±',
            'lat'        => 32.9014,
            'lng'        => 13.2000,
            'is_default' => true,
            'zone_id'    => $this->zoneIdBenAshour,
        ]);

        // â”€â”€ ط¹ظ†ظˆط§ظ† ظ…ظ†ط²ظ„ ط§ظ„ط·ظپظ„ ط§ظ„ط«ط§ظ†ظٹ (ط¹ط±ط§ط¯ط© â€“ ظ‚ط±ظٹط¨ ظ…ظ† ظ…ط¯ط±ط³ط© ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط©) â”€â”€
        // ط¥ط­ط¯ط§ط«ظٹط§طھ: ط¹ط±ط§ط¯ط©طŒ ط·ط±ط§ط¨ظ„ط³ (32.8760, 13.2350)
        DB::table('addresses')->insert([
            'parent_id'  => $userId,
            'label'      => 'ظ…ظ†ط²ظ„ ط¹ط±ط§ط¯ط©',
            'lat'        => 32.8760,
            'lng'        => 13.2350,
            'is_default' => false,
            'zone_id'    => $this->zoneIdArada,
        ]);

        return $userId;
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // [4] ط¥ظ†ط´ط§ط، ط§ظ„ط£ط·ظپط§ظ„ ظ…ط¹ ط¨ظٹط§ظ†ط§طھ ظ„ظˆط¬ط³طھظٹط© ظ…طھظ†ظˆط¹ط©
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function createChildren(int $parentId): void
    {
        // ط¬ظ„ط¨ ط§ظ„ط¹ظ†ط§ظˆظٹظ†
        $addresses = DB::table('addresses')->where('parent_id', $parentId)->get();
        $addr1 = $addresses->firstWhere('label', 'ظ…ظ†ط²ظ„ ط¨ظ† ط¹ط§ط´ظˆط±');
        $addr2 = $addresses->firstWhere('label', 'ظ…ظ†ط²ظ„ ط¹ط±ط§ط¯ط©');

        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // ط§ظ„ط·ظپظ„ 1: ط°ظƒط± | ط§ط´طھط±ط§ظƒ ط´ظ‡ط±ظٹ | ظ…ط¯ط±ط³ط© ط·ط±ط§ط¨ظ„ط³
        // ط§ظ„ط؛ط±ط¶: ط§ط®طھط¨ط§ط± ط§ظ„طھط³ط¹ظٹط± ط§ظ„ط´ظ‡ط±ظٹ + ط§ظ„ط³ظٹط§ط±ط© ط§ظ„ظ…ظƒظٹظپط©
        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $child1Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolTripoliId,
            'address_id'          => $addr1?->id,
            'full_name'           => 'ظٹظˆط³ظپ ظ…ط­ظ…ط¯ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
            'birth_date'          => '2015-03-10',
            'gender'              => 'male',
            'grade'               => 4,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST001-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child1Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '13:30:00',
            'trip_direction'      => 'both',
            'subscription_type' => 'multi_day',
            'start_date'          => '2026-09-01',
            'end_date'            => '2026-09-30',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // ط§ظ„ط·ظپظ„ 2: ط£ظ†ط«ظ‰ | ط§ط´طھط±ط§ظƒ ظٹظˆظ…ظٹ | ظ…ط¯ط±ط³ط© ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط©
        // ط§ظ„ط؛ط±ط¶: ط§ط®طھط¨ط§ط± ط§ظ„طھط³ط¹ظٹط± ط§ظ„ظٹظˆظ…ظٹ + ظپظ„طھط±ط© ط§ظ„ط¬ظ†ط³
        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $child2Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolSouqId,
            'address_id'          => $addr2?->id,
            'full_name'           => 'ط±ظٹظ… ظ…ط­ظ…ط¯ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
            'birth_date'          => '2017-07-22',
            'gender'              => 'female',
            'grade'               => 2,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST002-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child2Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:15:00',
            'dropoff_time'        => '13:00:00',
            'trip_direction'      => 'go',
            'subscription_type' => 'single_day',
            'start_date'          => '2026-09-05',
            'end_date'            => '2026-09-05',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // ط§ظ„ط·ظپظ„ 3: ط°ظƒط± | ط§ط´طھط±ط§ظƒ ط´ظ‡ط±ظٹ | ظ†ظپط³ ظ…ط¯ط±ط³ط© ط·ط±ط§ط¨ظ„ط³
        // ط§ظ„ط؛ط±ط¶: ط§ط®طھط¨ط§ط± ط£ظƒط«ط± ظ…ظ† ط·ظپظ„ (child_ids=[1,3])
        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $child3Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolTripoliId,
            'address_id'          => $addr1?->id,
            'full_name'           => 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
            'birth_date'          => '2013-11-15',
            'gender'              => 'male',
            'grade'               => 6,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST003-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child3Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '13:30:00',
            'trip_direction'      => 'both',
            'subscription_type' => 'multi_day',
            'start_date'          => '2026-09-01',
            'end_date'            => '2026-09-30',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // ط§ظ„ط·ظپظ„ 4: ط°ظƒط± | ط¨ط¯ظˆظ† ط¨ظٹط§ظ†ط§طھ ظ„ظˆط¬ط³طھظٹط© (edge case)
        // ط§ظ„ط؛ط±ط¶: ط§ط®طھط¨ط§ط± ط­ط§ظ„ط© ط§ظ„ط·ظپظ„ ط§ظ„ظ†ط§ظ‚طµ
        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => null,        // ط¨ط¯ظˆظ† ظ…ط¯ط±ط³ط©
            'address_id'          => null,        // ط¨ط¯ظˆظ† ط¹ظ†ظˆط§ظ†
            'full_name'           => 'ط³ظ„ظ…ط§ظ† ظ…ط­ظ…ط¯ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
            'birth_date'          => '2019-01-01',
            'gender'              => 'male',
            'grade'               => 1,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST004-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        // âڑ ï¸ڈ ظ‡ط°ط§ ط§ظ„ط·ظپظ„ ط¨ط¯ظˆظ† child_logistics ط¹ظ…ط¯ط§ظ‹ (edge case)
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // [5] ط¥ظ†ط´ط§ط، ط§ظ„ط³ط§ط¦ظ‚ظٹظ† ط§ظ„ظ…طھظ†ظˆط¹ظٹظ†
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function createDrivers(): void
    {
        $driversData = [
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 1: ط°ظƒط± | ظ…ظƒظٹظپ | ط²ظˆظ† ط¨ظ† ط¹ط§ط´ظˆط± | ط´ظ‡ط±ظٹ
            // â†گ ظ…ط«ط§ظ„ظٹ ظ„ط³ظٹظ†ط§ط±ظٹظˆ: ط·ظپظ„ ط°ظƒط± + ظ†ظپط³ ط§ظ„ظ…ظ†ط·ظ‚ط© + ظ…ظƒظٹظپ + ط´ظ‡ط±ظٹ
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ط®ط§ظ„ط¯ ظ…طµط·ظپظ‰ ط§ظ„ظˆط±ظپظ„ظٹ',
                    'email'        => 'khalid.driver@derbi.ly',
                    'phone_number' => '0917001001',
                    'alternative_phone' => '0910001001',
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'multi_day',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.8,
                    'completed_trips_count' => 120,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-5521',
                    'brand'           => 'Toyota',
                    'model'           => 'Hiace',
                    'year'            => '2022',
                    'color'           => 'ط£ط¨ظٹط¶',
                    'type'            => 'Van',
                    'capacity_manual' => 12,
                    'has_ac'          => 1,   // â†گ ظ…ظƒظٹظپ
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdBenAshour],  // ط¨ظ† ط¹ط§ط´ظˆط±
            ],

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 2: ط°ظƒط± | ط؛ظٹط± ظ…ظƒظٹظپ | ط²ظˆظ† ط§ظ„ط¸ظ‡ط±ط© | ط´ظ‡ط±ظٹ
            // â†گ ط§ط®طھط¨ط§ط±: ظ†ظپط³ ط§ظ„ط¨ظ„ط¯ظٹط© (ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©) + ط؛ظٹط± ظ…ظƒظٹظپ
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ط¹ط¨ط¯ ط§ظ„ط±ط­ظ…ظ† ط³ط§ظ„ظ… ط§ظ„ط²ط±ظˆظ‚',
                    'email'        => 'abdurahman.driver@derbi.ly',
                    'phone_number' => '0920002002',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'male',   // ظٹظ‚ط¨ظ„ ظپظ‚ط· ط§ظ„ط°ظƒظˆط±
                    'subscription_type' => 'multi_day',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.2,
                    'completed_trips_count' => 85,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-3344',
                    'brand'           => 'Hyundai',
                    'model'           => 'H1',
                    'year'            => '2020',
                    'color'           => 'ظپط¶ظٹ',
                    'type'            => 'Van',
                    'capacity_manual' => 8,
                    'has_ac'          => 0,   // â†گ ط؛ظٹط± ظ…ظƒظٹظپ
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdDahra],  // ط§ظ„ط¸ظ‡ط±ط© (ظ†ظپط³ ط¨ظ„ط¯ظٹط© ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط¯ظٹظ†ط©)
            ],

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 3: ط£ظ†ط«ظ‰ | ظ…ظƒظٹظپ | ط²ظˆظ† ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ | ظٹظˆظ…ظٹ + ط´ظ‡ط±ظٹ
            // â†گ ط§ط®طھط¨ط§ط±: ظپظ„طھط±ط© ط¬ظ†ط³ ط§ظ„ط³ط§ط¦ظ‚ ط£ظ†ط«ظ‰ + has_ac + daily
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ط³ظ„ظ…ظ‰ ط£ط­ظ…ط¯ ط§ظ„ظ…طµط±ط§طھظٹ',
                    'email'        => 'salma.driver@derbi.ly',
                    'phone_number' => '0913003003',
                    'alternative_phone' => '0920003003',
                ],
                'driver' => [
                    'gender'            => 'female',
                    'accepted_gender'   => 'female',  // طھظ‚ط¨ظ„ ظپظ‚ط· ط§ظ„ط¥ظ†ط§ط«
                    'subscription_type' => 'both',
                    'shift'             => 3,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.9,
                    'completed_trips_count' => 200,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-7788',
                    'brand'           => 'Kia',
                    'model'           => 'Carnival',
                    'year'            => '2023',
                    'color'           => 'ط£ط²ط±ظ‚',
                    'type'            => 'Van',
                    'capacity_manual' => 7,
                    'has_ac'          => 1,   // â†گ ظ…ظƒظٹظپ
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdAndalus],  // ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³
            ],

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 4: ط°ظƒط± | ظ…ظƒظٹظپ | ط²ظˆظ† ط¹ط±ط§ط¯ط© | ط´ظ‡ط±ظٹ
            // â†گ ط§ط®طھط¨ط§ط±: ظ†ظپط³ ظ…ظ†ط·ظ‚ط© ط§ظ„ط·ظپظ„ ط§ظ„ط«ط§ظ†ظٹ (ط¹ط±ط§ط¯ط©)
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ط¥ط¨ط±ط§ظ‡ظٹظ… ظ…ط­ظ…ظˆط¯ ط§ظ„ط¨ط¯ط±ظٹ',
                    'email'        => 'ibrahim.driver@derbi.ly',
                    'phone_number' => '0944004004',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'both',
                    'shift'             => 2,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.5,
                    'completed_trips_count' => 60,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-1199',
                    'brand'           => 'Nissan',
                    'model'           => 'Urvan',
                    'year'            => '2021',
                    'color'           => 'ط±ظ…ط§ط¯ظٹ',
                    'type'            => 'Van',
                    'capacity_manual' => 14,
                    'has_ac'          => 1,   // â†گ ظ…ظƒظٹظپ
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdArada],  // ط¹ط±ط§ط¯ط©
            ],

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 5: ط°ظƒط± | ط؛ظٹط± ظ…ظƒظٹظپ | ط¨ط¯ظˆظ† ظ…ظ†ط·ظ‚ط© | ظٹظˆظ…ظٹ
            // â†گ ط§ط®طھط¨ط§ط±: ط³ط§ط¦ظ‚ ظ„ط§ ظٹط؛ط·ظٹ ط£ظٹ ظ…ظ†ط·ظ‚ط© (ظ„ط§ ظٹط¸ظ‡ط± ظپظٹ ظپظ„طھط±ط© ط§ظ„ظ…ظ†ط·ظ‚ط©)
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ظ…ط­ظ…ظˆط¯ ط¹ظ„ظٹ ط§ظ„ظپظٹطھظˆط±ظٹ',
                    'email'        => 'mahmoud.fituri.driver@derbi.ly',
                    'phone_number' => '0955005005',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'single_day',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 3.9,
                    'completed_trips_count' => 30,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-6677',
                    'brand'           => 'Mercedes',
                    'model'           => 'Sprinter',
                    'year'            => '2019',
                    'color'           => 'ط£ط¨ظٹط¶',
                    'type'            => 'Bus',
                    'capacity_manual' => 20,
                    'has_ac'          => 0,   // â†گ ط؛ظٹط± ظ…ظƒظٹظپ
                    'status'          => 'Active',
                ],
                'zones' => [],  // â†گ ط¨ط¯ظˆظ† ظ…ظ†ط·ظ‚ط© (fallback test)
            ],

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // ط§ظ„ط³ط§ط¦ظ‚ 6: ط°ظƒط± | ظ…ظƒظٹظپ | ظ…ط¹ظ„ظ‚ (Suspended)
            // â†گ ط§ط®طھط¨ط§ط±: ظٹط¬ط¨ ط£ظ„ط§ ظٹط¸ظ‡ط± ظپظٹ ط§ظ„ظ†طھط§ط¦ط¬ (ط؛ظٹط± ظ…ط¹طھظ…ط¯)
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'user' => [
                    'full_name'    => 'ظپط±ط¬ ط§ظ„ظ„ظ‡ ط¹ط«ظ…ط§ظ† ط§ظ„ظ…ط¨ط±ظˆظƒ',
                    'email'        => 'farajallah.driver@derbi.ly',
                    'phone_number' => '0966006006',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'multi_day',
                    'shift'             => 1,
                    'status'            => 'Suspended',  // â†گ ظ…ط¹ظ„ظ‚ - ظٹط¬ط¨ ط£ظ„ط§ ظٹط¸ظ‡ط±!
                    'rating_avg'        => 4.0,
                    'completed_trips_count' => 10,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-9900',
                    'brand'           => 'Ford',
                    'model'           => 'Transit',
                    'year'            => '2018',
                    'color'           => 'ط£ط³ظˆط¯',
                    'type'            => 'Van',
                    'capacity_manual' => 9,
                    'has_ac'          => 1,
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdBenAshour],
            ],
        ];

        foreach ($driversData as $data) {
            $this->insertDriver($data);
        }
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Helper: ط¥ط¯ط±ط§ط¬ ط³ط§ط¦ظ‚ ظƒط§ظ…ظ„
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function insertDriver(array $data): void
    {
        $u = $data['user'];
        $d = $data['driver'];
        $v = $data['vehicle'];

        // â”€â”€ users â”€â”€
        $userId = DB::table('users')->insertGetId([
            'full_name'         => $u['full_name'],
            'email'             => $u['email'],
            'phone_number'      => $u['phone_number'],
            'alternative_phone' => $u['alternative_phone'] ?? null,
            'password_hash'     => $this->password,
            'role_id'           => $this->roleDriver,
            'is_active'         => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // â”€â”€ drivers â”€â”€
        $driverId = DB::table('drivers')->insertGetId([
            'user_id'                   => $userId,
            'gender'                    => $d['gender'],
            'accepted_gender'           => $d['accepted_gender'],
            'subscription_type'         => $d['subscription_type'],
            'shift'                     => $d['shift'],
            'status'                    => $d['status'],
            'rating_avg'                => $d['rating_avg'],
            'completed_trips_count'     => $d['completed_trips_count'],
            'active_subs_count'         => 0,
            'total_subs_count'          => 0,
            'cancelled_by_driver_count' => 0,
            'cancelled_by_parent_count' => 0,
            'retention_rate'            => 100.00,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        // â”€â”€ vehicles â”€â”€
        DB::table('vehicles')->insert([
            'driver_id'       => $driverId,
            'plate_number'    => $v['plate_number'],
            'brand'           => $v['brand'],
            'model'           => $v['model'],
            'year'            => $v['year'],
            'color'           => $v['color'],
            'type'            => $v['type'],
            'capacity_manual' => $v['capacity_manual'],
            'has_ac'          => $v['has_ac'],
            'status'          => $v['status'],
            'is_verified'     => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // â”€â”€ driver_zone â”€â”€
        foreach ($data['zones'] as $zoneId) {
            DB::table('driver_zone')->insert([
                'driver_id'  => $driverId,
                'zone_id'    => $zoneId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // ط·ط¨ط§ط¹ط© ظ…ظ„ط®طµ ط§ظ„ط¨ظٹط§ظ†ط§طھ ظ„ظ„ظ€ Terminal
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    private function printSummary(int $parentId): void
    {
        $children = DB::table('children')->where('parent_id', $parentId)->get();

        $this->command->newLine();
        $this->command->info('â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ');
        $this->command->info('  ًں“‹ ظ…ظ„ط®طµ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ظڈط¯ط®ظ„ط© ظ„ظ„ط§ط®طھط¨ط§ط±');
        $this->command->info('â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ');
        $this->command->info("  ًں‘¤ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ID : {$parentId}");
        $this->command->info("  ًں“§ Email        : parent.test@derbi.ly");
        $this->command->info("  ًں”‘ Password     : 12345678");
        $this->command->newLine();
        foreach ($children as $c) {
            $this->command->info("  ًں‘¶ ط·ظپظ„: {$c->full_name} | ID: {$c->id} | ط§ظ„ط¬ظ†ط³: {$c->gender}");
        }
        $this->command->newLine();
        $this->command->info('  ًںڑ— ط§ظ„ط³ط§ط¦ظ‚ظˆظ† ط§ظ„ظ…ظڈط¶ط§ظپظˆظ†: 6 ط³ط§ط¦ظ‚ظٹظ† (1 ظ…ط¹ظ„ظ‚ - ظٹط¬ط¨ ط£ظ„ط§ ظٹط¸ظ‡ط±)');
        $this->command->info('â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ');
    }
}
