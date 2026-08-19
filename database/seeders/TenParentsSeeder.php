<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Address;
use App\Models\Parent\School;
use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\DriverReview;
use App\Models\Shared\Complaint;
use App\Models\Shared\AbsenceLog;
use App\Models\Shared\Invoice;
use App\Models\Shared\RechargeRequest;
use Carbon\Carbon;

class TenParentsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        echo "ًںڑ€ ط¨ط¯ط§ظٹط© ط²ط±ط¹ 10 ط£ظˆظ„ظٹط§ط، ط£ظ…ظˆط± ظˆط§ط´طھط±ط§ظƒط§طھظ‡ظ… ظ…ط¹ ط§ظ„ط³ط§ط¦ظ‚ظٹظ† ط§ظ„ظ…ظˆط¬ظˆط¯ظٹظ† ظ„ظ„ظ†ط¸ط§ظ…...\n";

        // 1. ط¬ظ„ط¨ ط§ظ„ط³ط§ط¦ظ‚ظٹظ† ظˆط§ظ„ظ…ط¯ط§ط±ط³ ظˆط§ظ„ظ…ظ†ط§ط·ظ‚ ط§ظ„ظ…طھط§ط­ط© ط­ط§ظ„ظٹط§ظ‹ ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ
        $drivers = Driver::with('user')->get();
        if ($drivers->isEmpty()) {
            echo "â‌Œ ظ„ط§ ظٹظˆط¬ط¯ ط³ط§ط¦ظ‚ظˆظ† ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ! ظٹظڈط±ط¬ظ‰ طھط´ط؛ظٹظ„ FullSystemSeeder ط£ظˆظ„ط§ظ‹.\n";
            return;
        }

        $driver1 = $drivers->first(); // ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط£ظˆظ„ (ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…طµط±ط§طھظٹ)
        $driver2 = $drivers->skip(1)->first() ?? $driver1; // ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط«ط§ظ†ظٹ (ط·ط§ظ‡ط± ط§ظ„ط²ظ†طھط§ظ†ظٹ)

        $school1 = School::first() ?? School::create([
            'name' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯ ط§ظ„ط¯ظˆظ„ظٹط©',
            'lat' => 32.89000000,
            'lng' => 13.17000000,
            'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³',
            'status' => 'approved'
        ]);

        $school2 = School::skip(1)->first() ?? $school1;
        $zoneId = DB::table('zones')->value('id') ?? 1;
        $adminId = DB::table('admins')->value('id') ?? 1;

        // 2. ظ‚ط§ط¦ظ…ط© ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ€ 10 ط£ظˆظ„ظٹط§ط، ط£ظ…ظˆط±
        $parentsData = [
            [
                'full_name' => 'ط³ط§ظ„ظ… ظپطھط­ظٹ ط§ظ„ط¨ظˆط³ظٹظپظٹ',
                'email' => 'parent3@darby.com',
                'phone' => '0913333333',
                'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ظ…طµط­ط© ط§ظ„ظ…ط³ط±ط©',
                'lat' => 32.89300000,
                'lng' => 13.17600000,
                'children' => [
                    ['name' => 'ظ…ط­ظ…ط¯ ط³ط§ظ„ظ… ط§ظ„ط¨ظˆط³ظٹظپظٹ', 'birth' => '2016-03-12', 'gender' => 'male', 'grade' => 3],
                    ['name' => 'ظپط§ط·ظ…ط© ط³ط§ظ„ظ… ط§ظ„ط¨ظˆط³ظٹظپظٹ', 'birth' => '2014-06-25', 'gender' => 'female', 'grade' => 5],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ط³ط§ط¦ظ‚ ظ…ظ…طھط§ط² ظˆط®ظ„ظˆظ‚ ط¬ط¯ط§ظ‹طŒ ط§ظ„ط§ظ„طھط²ط§ظ… ط¨ط§ظ„ظ…ظˆط§ط¹ظٹط¯ ظ…ظ…طھط§ط².',
                'complaint' => null
            ],
            [
                'full_name' => 'ط·ط§ط±ظ‚ ظ…طµط·ظپظ‰ ط§ظ„طھط§ط¬ظˆط±ظٹ',
                'email' => 'parent4@darby.com',
                'phone' => '0914444444',
                'address' => 'ط¨ظ† ط¹ط§ط´ظˆط± - ط®ظ„ظپ ط¬ط§ظ…ط¹ ط§ظ„طµظ‚ط¹',
                'lat' => 32.90300000,
                'lng' => 13.21800000,
                'children' => [
                    ['name' => 'ط¹ظ„ظٹ ط·ط§ط±ظ‚ ط§ظ„طھط§ط¬ظˆط±ظٹ', 'birth' => '2017-09-10', 'gender' => 'male', 'grade' => 2],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'ط§ظ„ط±ط­ظ„ط© ظ…ط±ظٹط­ط© ظˆط§ظ„طھظƒظٹظٹظپ ظٹط¹ظ…ظ„ ط¨ط´ظƒظ„ ظ…ظ…طھط§ط².',
                'complaint' => null
            ],
            [
                'full_name' => 'ط¹ظ…ط± ط®ط§ظ„ط¯ ط§ظ„ط²ط§ظˆظٹ',
                'email' => 'parent5@darby.com',
                'phone' => '0915555555',
                'address' => 'ط§ظ„ط³ظٹط§ط­ظٹط© - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ظ…ط¬ظ…ط¹ ط§ظ„ظ…ظ‡ظ† ط§ظ„ظ…ظˆط³ظٹظ‚ظٹط©',
                'lat' => 32.89150000,
                'lng' => 13.17200000,
                'children' => [
                    ['name' => 'ظٹظˆط³ظپ ط¹ظ…ط± ط§ظ„ط²ط§ظˆظٹ', 'birth' => '2015-11-05', 'gender' => 'male', 'grade' => 4],
                    ['name' => 'ط¹ط§ط¦ط´ط© ط¹ظ…ط± ط§ظ„ط²ط§ظˆظٹ', 'birth' => '2018-01-30', 'gender' => 'female', 'grade' => 1],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ط®ط¯ظ…ط© ظ…ظ…طھط§ط²ط© ظˆط§ظ„طھظˆط§طµظ„ ظ…ط¹ ط§ظ„ط³ط§ط¦ظ‚ ط³ظ‡ظ„ ظˆظ…ط¨ط§ط´ط±.',
                'complaint' => [
                    'desc' => 'طھط£ط®ط± ط§ظ„ط³ط§ط¦ظ‚ 15 ط¯ظ‚ظٹظ‚ط© ط¹ظ† ظ…ظˆط¹ط¯ ط§ظ„ط­ط§ظپظ„ط© ط§ظ„طµط¨ط§ط­ظٹ ظٹظˆظ… ط§ظ„ط«ظ„ط§ط«ط§ط، ط§ظ„ظ…ط§ط¶ظٹ.',
                    'status' => 'completed',
                    'action' => 'warning',
                    'details' => 'طھظ… ط§ظ„طھظˆط§طµظ„ ظ…ط¹ ط§ظ„ط³ط§ط¦ظ‚ ظˆطھظ†ط¨ظٹظ‡ظ‡ ظ„ظ„ط§ظ„طھط²ط§ظ… ط¨ط§ظ„ط¬ط¯ظˆظ„ ط§ظ„ط²ظ…ظ†ظٹ.'
                ]
            ],
            [
                'full_name' => 'ظ‡ط´ط§ظ… ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„ط´ط±ظٹظپ',
                'email' => 'parent6@darby.com',
                'phone' => '0916666666',
                'address' => 'ط§ظ„ظ†ظˆظپظ„ظٹظٹظ† - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط§ظ„ظپظ†ط§ط±',
                'lat' => 32.89800000,
                'lng' => 13.20500000,
                'children' => [
                    ['name' => 'ط£ط­ظ…ط¯ ظ‡ط´ط§ظ… ط§ظ„ط´ط±ظٹظپ', 'birth' => '2016-07-18', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver2,
                'status' => 'pending',
                'rating' => null,
                'review' => null,
                'complaint' => null
            ],
            [
                'full_name' => 'ظ…طµط·ظپظ‰ ط¹ط§ط¯ظ„ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
                'email' => 'parent7@darby.com',
                'phone' => '0917777777',
                'address' => 'ط·ط±ظٹظ‚ ط§ظ„ط´ط· - ط®ظپ ظ…ط·ط¹ظ… ط¨ط±ط¬ ط§ظ„ظپط§طھط­',
                'lat' => 32.89700000,
                'lng' => 13.18500000,
                'children' => [
                    ['name' => 'ط³ط§ط±ط© ظ…طµط·ظپظ‰ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ', 'birth' => '2014-04-14', 'gender' => 'female', 'grade' => 5],
                    ['name' => 'ظ†ظˆط± ظ…طµط·ظپظ‰ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ', 'birth' => '2017-08-22', 'gender' => 'female', 'grade' => 2],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ط³ط§ط¦ظ‚ ظ…ط­طھط±ظ… ط¬ط¯ط§ظ‹ ظˆظ†ظ†طµط­ ط¨ط§ظ„طھط¹ط§ظ…ظ„ ظ…ط¹ظ‡.',
                'complaint' => null
            ],
            [
                'full_name' => 'ط¹ط¨ط¯ ط§ظ„ظˆظ‡ط§ط¨ ط¥ط¨ط±ط§ظ‡ظٹظ… ط§ظ„ظ…ظ‚ط±ط­ظٹ',
                'email' => 'parent8@darby.com',
                'phone' => '0918888888',
                'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط§ظ„ط´ط§ط±ط¹ ط§ظ„ط؛ط±ط¨ظٹ',
                'lat' => 32.89400000,
                'lng' => 13.17800000,
                'children' => [
                    ['name' => 'ط¥ط¨ط±ط§ظ‡ظٹظ… ط¹ط¨ط¯ ط§ظ„ظˆظ‡ط§ط¨ ط§ظ„ظ…ظ‚ط±ط­ظٹ', 'birth' => '2016-01-11', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'ط§ظ„ط­ط§ظپظ„ط© ظ†ط¸ظٹظپط© ظˆط§ظ„ط±ط­ظ„ط© ط¢ظ…ظ†ط© ظ„ظ„ط§ط·ظپط§ظ„.',
                'complaint' => [
                    'desc' => 'طھط¬ط§ظˆط² ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط³ط±ط¹ط© ط§ظ„ظ…ط­ط¯ط¯ط© ط£ط«ظ†ط§ط، ط±ط­ظ„ط© ط§ظ„ط¹ظˆط¯ط© ط¸ظ‡ط± ط£ظ…ط³.',
                    'status' => 'pending',
                    'action' => 'none',
                    'details' => null
                ]
            ],
            [
                'full_name' => 'ظپطھط­ظٹ ط®ظ„ظٹظپط© ط§ظ„ظ‚ط±ظ‚ظ†ظٹ',
                'email' => 'parent9@darby.com',
                'phone' => '0919999999',
                'address' => 'ظ‚ط±ط¬ظٹ - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط§ظ„ط¯ظˆط§ط±',
                'lat' => 32.88500000,
                'lng' => 13.16000000,
                'children' => [
                    ['name' => 'ظ…ط±ظٹظ… ظپطھط­ظٹ ط§ظ„ظ‚ط±ظ‚ظ†ظٹ', 'birth' => '2015-05-19', 'gender' => 'female', 'grade' => 4],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ظ…ظ…طھط§ط² ط¬ط¯ط§ظ‹ ظˆط£ط®ظ„ط§ظ‚ ط¹ط§ظ„ظٹط© ظپظٹ ط§ظ„طھط¹ط§ظ…ظ„ ظ…ط¹ ط§ظ„ط£ط·ظپط§ظ„.',
                'complaint' => null
            ],
            [
                'full_name' => 'ظˆظ„ظٹط¯ ظپط±ط¬ ط§ظ„ط³ظˆظٹط­ظ„ظٹ',
                'email' => 'parent10@darby.com',
                'phone' => '0910101010',
                'address' => 'ط²ط§ظˆظٹط© ط§ظ„ط¯ظ‡ظ…ط§ظ†ظٹ - ظ‚ط±ط¨ ظ…ظٹظ†ط§ط، ط·ط±ط§ط¨ظ„ط³',
                'lat' => 32.90000000,
                'lng' => 13.19500000,
                'children' => [
                    ['name' => 'ظپط±ط¬ ظˆظ„ظٹط¯ ط§ظ„ط³ظˆظٹط­ظ„ظٹ', 'birth' => '2017-02-28', 'gender' => 'male', 'grade' => 2],
                    ['name' => 'ظ‡ط¯ظ‰ ظˆظ„ظٹط¯ ط§ظ„ط³ظˆظٹط­ظ„ظٹ', 'birth' => '2019-10-15', 'gender' => 'female', 'grade' => 1],
                ],
                'driver' => $driver2,
                'status' => 'pending',
                'rating' => null,
                'review' => null,
                'complaint' => null
            ],
            [
                'full_name' => 'ط­ط³ط§ظ… ظ†ظˆط±ظٹ ط§ظ„ط؛ط±ظٹط§ظ†ظٹ',
                'email' => 'parent11@darby.com',
                'phone' => '0910202020',
                'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط¹ظ…ط§ط±ط§طھ ط§ظ„طھط§ظ…ظٹظ†',
                'lat' => 32.89100000,
                'lng' => 13.17400000,
                'children' => [
                    ['name' => 'ظ†ظˆط±ظٹ ط­ط³ط§ظ… ط§ظ„ط؛ط±ظٹط§ظ†ظٹ', 'birth' => '2016-12-04', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ط³ط§ط¦ظ‚ ظ…ظ…طھط§ط² ظˆظ†ط¸ط§ظ… ط§ظ„طھطھط¨ط¹ ط§ظ„ط¯ظ‚ظٹظ‚ ظٹظ…ظ†ط­ظ†ط§ ط±ط§ط­ط© ط¨ط§ظ„ ظƒط§ظ…ظ„ط©.',
                'complaint' => null
            ],
            [
                'full_name' => 'ظ†ط§ط¬ظٹ ط¹ط«ظ…ط§ظ† ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                'email' => 'parent12@darby.com',
                'phone' => '0910303030',
                'address' => 'ظپط´ظ„ظˆظ… - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط§ظ„ظ…ط³طھظˆطµظپ',
                'lat' => 32.89600000,
                'lng' => 13.21000000,
                'children' => [
                    ['name' => 'ط¹ط«ظ…ط§ظ† ظ†ط§ط¬ظٹ ط§ظ„طھط±ظ‡ظˆظ†ظٹ', 'birth' => '2015-08-08', 'gender' => 'male', 'grade' => 4],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'طھط¹ط§ظ…ظ„ ظ…ظ‡ظ†ظٹ ظˆط±ط§ظ‚ظٹ ظ…ظ† ظ‚ط¨ظ„ ط§ظ„ط³ط§ط¦ظ‚.',
                'complaint' => null
            ],
        ];

        $counter = 100;

        foreach ($parentsData as $index => $data) {
            $counter++;

            // طھظ†ط¸ظٹظپ ط§ظ„ط­ط³ط§ط¨ ط§ظ„ظ‚ط¯ظٹظ… ط¥ظ† ظˆط¬ط¯ ظ„طھظپط§ط¯ظٹ ط£ظٹ طھط¶ط§ط±ط¨ ط¯ظˆظ† ط§ظ„ظ…ط³ط§ط³ ط¨ط¨ط§ظ‚ظٹ ط§ظ„ط¬ط¯ظˆظ„
            $oldUser = User::where('email', $data['email'])->first();
            if ($oldUser) {
                $oldParent = ParentModel::where('user_id', $oldUser->id)->first();
                if ($oldParent) {
                    $oldSubReqs = SubscriptionRequest::where('parent_id', $oldParent->id)->pluck('id');
                    DB::table('request_children')->whereIn('request_id', $oldSubReqs)->delete();
                    SubscriptionRequest::where('parent_id', $oldParent->id)->delete();
                    Complaint::where('submitted_by', $oldParent->id)->delete();
                    $oldParent->delete();
                }
                Contract::where('parent_id', $oldUser->id)->delete();
                ActiveSubscription::where('parent_id', $oldUser->id)->delete();
                DriverReview::where('parent_id', $oldUser->id)->delete();
                Address::where('parent_id', $oldUser->id)->delete();
                DB::table('wallets')->where('holder_id', $oldUser->id)->delete();
                $oldUser->forceDelete();
            }

            // ط£) ط¥ظ†ط´ط§ط، ط­ط³ط§ط¨ ط§ظ„ظ…ط³طھط®ط¯ظ… ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone'],
                'password_hash' => Hash::make('12345678'),
                'role_id' => 3, // ظˆظ„ظٹ ط£ظ…ط±
                'is_active' => 1,
                'email_verified_at' => now(),
                'phone_verified_at' => now()
            ]);

            // ط¨) ط¥ظ†ط´ط§ط، ظ†ظ…ظˆط°ط¬ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            $parentModel = ParentModel::create([
                'user_id' => $user->id,
                'is_trusted' => 1
            ]);

            // ط¬) ط¥ظ†ط´ط§ط، ط¹ظ†ظˆط§ظ† ط§ظ„ط³ظƒظ†
            $address = Address::create([
                'parent_id' => $user->id,
                'label' => $data['address'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'is_default' => true,
                'zone_id' => $zoneId
            ]);

            // ط¯) ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط­ظپط¸ط© ط§ظ„ظ…ط§ظ„ظٹط© ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            DB::table('wallets')->insert([
                'holder_type' => 'App\Models\User',
                'holder_id' => $user->id,
                'name' => 'ط§ظ„ظ…ط­ظپط¸ط© ط§ظ„ط±ط¦ظٹط³ظٹط©',
                'slug' => 'default',
                'uuid' => Str::uuid()->toString(),
                'balance' => rand(150, 500),
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // ظ‡ظ€) ط¥ظ†ط´ط§ط، ط§ظ„ط£ط·ظپط§ظ„
            $createdChildren = [];
            $school = ($index % 2 === 0) ? $school1 : $school2;

            foreach ($data['children'] as $childData) {
                $child = Child::create([
                    'parent_id' => $user->id,
                    'school_id' => $school->id,
                    'address_id' => $address->id,
                    'full_name' => $childData['name'],
                    'birth_date' => $childData['birth'],
                    'gender' => $childData['gender'],
                    'grade' => $childData['grade']
                ]);
                $createdChildren[] = $child;
            }

            // ظˆ) ط¥ظ†ط´ط§ط، ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ ظˆط§ظ„ط¹ظ‚ط¯ ظ„ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ…ظ‚ط¨ظˆظ„ط©
            $driver = $data['driver'];
            $reqStatus = ($data['status'] === 'active') ? SubscriptionRequest::STATUS_ACCEPTED : SubscriptionRequest::STATUS_PENDING;

            $subRequest = SubscriptionRequest::create([
                'parent_id' => $parentModel->id,
                'driver_id' => $driver->id,
                'school_id' => $school->id,
                'subscription_type' => 'multi_day',
                'direction' => 'two_way',
                'timing' => 'morning',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->addDays(30)->toDateString(),
                'days_count' => 22,
                'total_price' => count($createdChildren) * 300.00,
                'pickup_time' => '07:00:00',
                'dropoff_time' => '14:00:00',
                'max_waiting_time' => 15,
                'status' => $reqStatus,
                'notes' => 'ظٹط±ط¬ظ‰ طھظˆط®ظٹ ط§ظ„ط­ط°ط± ط¹ظ†ط¯ ط§ظ„طھظˆظ‚ظپ ط£ظ…ط§ظ… ط§ظ„ظ…ظ†ط²ظ„',
                'children_count' => count($createdChildren)
            ]);

            foreach ($createdChildren as $childObj) {
                DB::table('request_children')->insert([
                    'request_id' => $subRequest->id,
                    'child_id' => $childObj->id,
                    'pickup_address_id' => $address->id,
                    'dropoff_address_id' => $address->id,
                    'home_lat' => $data['lat'],
                    'home_lng' => $data['lng'],
                    'home_label' => 'ظ…ظ†ط²ظ„ ' . $data['full_name'],
                    'school_lat' => $school->lat ?? 32.89000000,
                    'school_lng' => $school->lng ?? 13.17000000,
                    'school_label' => $school->name,
                    'price_per_child' => 300.00
                ]);
            }

            if ($data['status'] === 'active') {
                $contractNum = 'CNT-2026-' . $counter;
                $contract = Contract::create([
                    'subscription_request_id' => $subRequest->id,
                    'parent_id' => $user->id,
                    'driver_id' => $driver->user_id ?? $driver->id,
                    'contract_number' => $contractNum,
                    'subscription_type' => 'multi_day',
                    'direction' => 'two_way',
                    'timing' => 'morning',
                    'pickup_time' => '07:00:00',
                    'dropoff_time' => '14:00:00',
                    'max_waiting_time' => 15,
                    'start_date' => Carbon::today()->toDateString(),
                    'end_date' => Carbon::today()->addDays(30)->toDateString(),
                    'days_count' => 22,
                    'total_price' => count($createdChildren) * 300.00,
                    'status' => 'active',
                    'signed_at' => now()
                ]);

                foreach ($createdChildren as $childObj) {
                    ActiveSubscription::create([
                        'contract_id' => $contract->id,
                        'child_id' => $childObj->id,
                        'driver_id' => $driver->id,
                        'parent_id' => $user->id,
                        'pickup_lat' => $data['lat'],
                        'pickup_lng' => $data['lng'],
                        'pickup_label' => 'ظ…ظ†ط²ظ„ ' . $data['full_name'],
                        'dropoff_lat' => $school->lat ?? 32.89000000,
                        'dropoff_lng' => $school->lng ?? 13.17000000,
                        'dropoff_label' => $school->name,
                        'pickup_time' => '07:00:00',
                        'dropoff_time' => '14:00:00',
                        'status' => 'active'
                    ]);
                }

                // ظپط§طھظˆط±ط© ظ„ظ„ط¹ظ‚ط¯
                Invoice::create([
                    'contract_id' => $contract->id,
                    'parent_id' => $user->id,
                    'driver_id' => $driver->id,
                    'invoice_number' => 'INV-2026-' . $counter,
                    'amount' => count($createdChildren) * 300.00,
                    'status' => 'paid',
                    'type' => 'monthly',
                    'due_date' => Carbon::today()->addDays(5)->toDateString(),
                    'paid_at' => now()
                ]);
            }

            // ط²) ط¥ظ†ط´ط§ط، ط§ظ„طھظ‚ظٹظٹظ…ط§طھ ط¥ظ† ظˆط¬ط¯طھ
            if ($data['rating']) {
                DriverReview::create([
                    'parent_id' => $user->id,
                    'driver_id' => $driver->id,
                    'contract_id' => isset($contract) ? $contract->id : null,
                    'rating' => $data['rating'],
                    'comment' => $data['review'],
                    'status' => 'active'
                ]);
            }

            // ط­) ط¥ظ†ط´ط§ط، ط§ظ„ط´ظƒط§ظˆظ‰ ط¥ظ† ظˆط¬ط¯طھ
            if ($data['complaint']) {
                Complaint::create([
                    'submitted_by' => $parentModel->id,
                    'against_type' => 'DRIVER',
                    'against_id' => $driver->id,
                    'driver_id' => $driver->id,
                    'description' => $data['complaint']['desc'],
                    'status' => $data['complaint']['status'],
                    'action_taken' => $data['complaint']['action'],
                    'action_details' => $data['complaint']['details'],
                    'resolved_by' => ($data['complaint']['status'] === 'completed') ? $adminId : null,
                    'resolved_at' => ($data['complaint']['status'] === 'completed') ? now() : null
                ]);
            }

            // ط·) ط¥ط¶ط§ظپط© طھط³ط¬ظٹظ„ ط؛ظٹط§ط¨ ظ„ط£ط­ط¯ ط§ظ„ط£ط·ظپط§ظ„ ظƒط³ظٹظ†ط§ط±ظٹظˆ ظˆط§ظ‚ط¹ظٹ
            if ($index === 2 && !empty($createdChildren)) {
                AbsenceLog::create([
                    'child_id' => $createdChildren[0]->id,
                    'absence_date' => Carbon::tomorrow()->toDateString()
                ]);
            }

            // ظٹ) ط¥ط¶ط§ظپط© ط·ظ„ط¨ ط´ط­ظ† ظ…ط­ظپط¸ط© ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط± ظƒط³ظٹظ†ط§ط±ظٹظˆ ظˆط§ظ‚ط¹ظٹ
            if ($index === 5) {
                RechargeRequest::create([
                    'parent_id' => $user->id,
                    'amount' => 150.00,
                    'payment_method' => 'Bank Transfer',
                    'status' => 'pending',
                    'reference_number' => 'REF-TEN-' . $counter
                ]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "ًںژ‰ طھظ… ط²ط±ط¹ 10 ط£ظˆظ„ظٹط§ط، ط£ظ…ظˆط± ط¨ظ†ط¬ط§ط­ ظ…ط¹ ظƒط§ظپط© ط£ط·ظپط§ظ„ظ‡ظ…طŒ ط§ط´طھط±ط§ظƒط§طھظ‡ظ…طŒ ط¹ظ‚ظˆط¯ظ‡ظ…طŒ طھظ‚ظٹظٹظ…ط§طھظ‡ظ…طŒ ظˆط´ظƒط§ظˆظٹظ‡ظ… ط¯ظˆظ† ط§ظ„ظ…ط³ط§ط³ ط¨ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ!\n";
    }
}
