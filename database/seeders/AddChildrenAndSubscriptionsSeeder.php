<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
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
use Carbon\Carbon;

class AddChildrenAndSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        echo "ًںڑ€ ط¨ط¯ط، ط¥ط¶ط§ظپط© ط§ظ„ط£ط·ظپط§ظ„ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظ„ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ط§ظ„ظ…ظˆط¬ظˆط¯ظٹظ† ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ط¨ط¯ظˆظ† ظ…ط³ط­ ط£ظٹ ط¨ظٹط§ظ†ط§طھ...\n";

        // 1. ط¬ظ„ط¨ ط§ظ„ظ…ط¯ط§ط±ط³ ظˆط§ظ„ط³ط§ط¦ظ‚ظٹظ† ظˆط§ظ„ظ…ظ†ط§ط·ظ‚ ط§ظ„ظ…طھط§ط­ط©
        $drivers = Driver::with('user')->get();
        if ($drivers->isEmpty()) {
            echo "â‌Œ ظ„ط§ ظٹظˆط¬ط¯ ط³ط§ط¦ظ‚ظˆظ† ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ! ظٹظڈط±ط¬ظ‰ ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ط³ط§ط¦ظ‚ظٹظ† ط£ظˆظ„ط§ظ‹.\n";
            return;
        }

        $schools = School::all();
        if ($schools->isEmpty()) {
            $school1 = School::create([
                'name' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯ ط§ظ„ط¯ظˆظ„ظٹط©',
                'lat' => 32.89000000,
                'lng' => 13.17000000,
                'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط·ط±ط§ط¨ظ„ط³',
                'status' => 'approved'
            ]);
            $schools = collect([$school1]);
        }
        $school1 = $schools->first();
        $school2 = $schools->skip(1)->first() ?? $school1;

        $zoneId = DB::table('zones')->value('id') ?? 1;

        // 2. ط¬ظ„ط¨ ظƒط§ظپط© ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ط§ظ„ظ…ط³ط¬ظ„ظٹظ† ظپظٹ ط§ظ„ظ†ط¸ط§ظ…
        $parents = ParentModel::with('user')->get();

        if ($parents->isEmpty()) {
            $parentUsers = User::where('role_id', 3)->get();
            foreach ($parentUsers as $pUser) {
                ParentModel::firstOrCreate(['user_id' => $pUser->id], ['is_trusted' => 1]);
            }
            $parents = ParentModel::with('user')->get();
        }

        if ($parents->isEmpty()) {
            echo "â‌Œ ظ„ط§ ظٹظˆط¬ط¯ ط£ظˆظ„ظٹط§ط، ط£ظ…ظˆط± ظپظٹ ط§ظ„ظ†ط¸ط§ظ… ظ„ط±ط¨ط·ظ‡ظ…!\n";
            return;
        }

        $sampleChildrenData = [
            [
                ['name' => 'ط¹ظ„ظٹ {lastname}', 'birth' => '2016-04-10', 'gender' => 'male', 'grade' => 3, 'notes' => 'ظ„ط§ طھظˆط¬ط¯ ظ…ظ„ط§ط­ط¸ط§طھ ط·ط¨ظٹط©'],
                ['name' => 'ط³ط§ط±ط© {lastname}', 'birth' => '2018-09-15', 'gender' => 'female', 'grade' => 1, 'notes' => 'ط­ط³ط§ط³ظٹط© ط¨ط³ظٹط·ط© ظ…ظ† ط§ظ„ط؛ط¨ط§ط±']
            ],
            [
                ['name' => 'ظ…ط­ظ…ط¯ {lastname}', 'birth' => '2015-03-12', 'gender' => 'male', 'grade' => 4, 'notes' => 'ظٹط±طھط¯ظٹ ظ†ط¸ط§ط±ط§طھ ط·ط¨ظٹط©'],
                ['name' => 'ظپط§ط·ظ…ط© {lastname}', 'birth' => '2017-06-25', 'gender' => 'female', 'grade' => 2, 'notes' => 'ظ„ط§ طھظˆط¬ط¯']
            ],
            [
                ['name' => 'ط£ظ†ط³ {lastname}', 'birth' => '2016-11-20', 'gender' => 'male', 'grade' => 3, 'notes' => 'ظ„ط§ طھظˆط¬ط¯']
            ],
            [
                ['name' => 'ظٹظˆط³ظپ {lastname}', 'birth' => '2015-08-05', 'gender' => 'male', 'grade' => 4, 'notes' => 'ظ„ط§ طھظˆط¬ط¯'],
                ['name' => 'ط¹ط§ط¦ط´ط© {lastname}', 'birth' => '2018-01-30', 'gender' => 'female', 'grade' => 1, 'notes' => 'ظ„ط§ طھظˆط¬ط¯']
            ],
            [
                ['name' => 'ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ {lastname}', 'birth' => '2014-07-14', 'gender' => 'male', 'grade' => 5, 'notes' => 'ظ„ط§ طھظˆط¬ط¯']
            ],
            [
                ['name' => 'ط·ط§ط±ظ‚ {lastname}', 'birth' => '2017-02-18', 'gender' => 'male', 'grade' => 2, 'notes' => 'ظ„ط§ طھظˆط¬ط¯'],
                ['name' => 'ظ„ظٹظ„ظ‰ {lastname}', 'birth' => '2019-05-10', 'gender' => 'female', 'grade' => 1, 'notes' => 'ظ„ط§ طھظˆط¬ط¯']
            ]
        ];

        $counter = 300;

        foreach ($parents as $index => $parentModel) {
            $user = $parentModel->user;
            if (!$user) continue;

            $counter++;

            // ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ط¹ظ†ظˆط§ظ† ط³ظƒظ† ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            $address = Address::where('parent_id', $user->id)->first();
            if (!$address) {
                $address = Address::create([
                    'parent_id'  => $user->id,
                    'label'      => 'ظ…ظ†ط²ظ„ ' . ($user->full_name ?? $user->name),
                    'lat'        => 32.89000000 + ($index * 0.002),
                    'lng'        => 13.17000000 + ($index * 0.002),
                    'is_default' => true,
                    'zone_id'    => $zoneId
                ]);
            }

            // ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ظ…ط­ظپط¸ط© ظ…ط§ظ„ظٹط©
            $hasWallet = DB::table('wallets')->where('holder_id', $user->id)->exists();
            if (!$hasWallet) {
                DB::table('wallets')->insert([
                    'holder_type'    => 'App\Models\User',
                    'holder_id'      => $user->id,
                    'name'           => 'ط§ظ„ظ…ط­ظپط¸ط© ط§ظ„ط±ط¦ظٹط³ظٹط©',
                    'slug'           => 'default-' . $user->id,
                    'uuid'           => Str::uuid()->toString(),
                    'balance'        => rand(200, 600),
                    'decimal_places' => 2,
                    'created_at'     => now(),
                    'updated_at'     => now()
                ]);
            }

            // ظپط­طµ ط§ظ„ط£ط·ظپط§ظ„ ط§ظ„ط­ط§ظ„ظٹظٹظ† ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            $existingChildren = Child::where('parent_id', $parentModel->id)->get();

            // ط§ط®طھط§ط± ط§ظ„ظ…ط¯ط±ط³ط© ظˆط§ظ„ط³ط§ط¦ظ‚ ط¨ط§ظ„طھظ†ط§ظˆط¨
            $school = ($index % 2 === 0) ? $school1 : $school2;
            $driver = $drivers->get($index % $drivers->count());

            // ط¥ظ†ط´ط§ط، ط£ط·ظپط§ظ„ ط¥ط°ط§ ظ„ظ… ظٹظƒظ† ظ„ط¯ظٹظ‡ ط£ط·ظپط§ظ„
            if ($existingChildren->isEmpty()) {
                $nameParts = explode(' ', trim($user->full_name ?? $user->name));
                $lastName = count($nameParts) > 1 ? end($nameParts) : 'ط§ظ„طھط±ظ‡ظˆظ†ظٹ';

                $childrenTemplate = $sampleChildrenData[$index % count($sampleChildrenData)];
                $createdChildren = [];

                foreach ($childrenTemplate as $cData) {
                    $cName = str_replace('{lastname}', $lastName, $cData['name']);
                    $child = Child::create([
                        'parent_id'     => $parentModel->id,
                        'school_id'     => $school->id,
                        'address_id'    => $address->id,
                        'full_name'     => $cName,
                        'birth_date'    => $cData['birth'],
                        'gender'        => $cData['gender'],
                        'grade'         => $cData['grade'],
                        'medical_notes' => $cData['notes']
                    ]);
                    $createdChildren[] = $child;
                }
            } else {
                $createdChildren = $existingChildren->all();
            }

            // ظپط­طµ ظˆط¬ظˆط¯ ط§ط´طھط±ط§ظƒ ظپط¹ط§ظ„ ط£ظˆ ط·ظ„ط¨ ط³ط§ط¨ظ‚ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط± طھط¬ظ†ط¨ط§ظ‹ ظ„ظ„طھظƒط±ط§ط±
            $hasActiveSub = ActiveSubscription::where('parent_id', $user->id)->exists();
            if ($hasActiveSub) {
                echo "â„¹ï¸ڈ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ({$user->full_name}) ظ„ط¯ظٹظ‡ ط§ط´طھط±ط§ظƒ ظ†ط´ط· ط³ط§ط¨ظ‚ط§ظ‹. طھظ… طھط®ط·ظٹظ‡.\n";
                continue;
            }

            // طھط­ط¯ظٹط¯ ط­ط§ظ„ط© ط§ظ„ط§ط´طھط±ط§ظƒ (ط؛ط§ظ„ط¨ظٹط© ظ…ظ‚ط¨ظˆظ„ط© ظˆظ†ط´ط·ط©طŒ ظˆط¨ط¹ط¶ظ‡ط§ ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط±)
            $isPending = ($index % 5 === 4); // ظƒظ„ 5 ط£ط³ط±طŒ ظˆط§ط­ط¯ط© طھظƒظˆظ† pending
            $reqStatus = $isPending ? SubscriptionRequest::STATUS_PENDING : SubscriptionRequest::STATUS_ACCEPTED;

            // 1. ط¥ظ†ط´ط§ط، ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ
            $subRequest = SubscriptionRequest::create([
                'parent_id'         => $parentModel->id,
                'driver_id'         => $driver->id,
                'school_id'         => $school->id,
                'subscription_type' => 'multi_day',
                'direction'         => 'two_way',
                'timing'            => 'morning',
                'start_date'        => Carbon::today()->toDateString(),
                'end_date'          => Carbon::today()->addDays(30)->toDateString(),
                'days_count'        => 22,
                'total_price'       => count($createdChildren) * 300.00,
                'pickup_time'       => '07:00:00',
                'dropoff_time'      => '14:00:00',
                'max_waiting_time'  => 15,
                'status'            => $reqStatus,
                'notes'             => 'ظٹط±ط¬ظ‰ طھظˆط®ظٹ ط§ظ„ط­ط°ط± ظˆط§ظ„ط§ظ„طھط²ط§ظ… ط¨ط§ظ„ظ…ظˆط§ط¹ظٹط¯ ط£ظ…ط§ظ… ط§ظ„ظ…ظ†ط²ظ„',
                'children_count'    => count($createdChildren)
            ]);

            // 2. ط±ط¨ط· ط§ظ„ط£ط·ظپط§ظ„ ط¨ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ
            foreach ($createdChildren as $childObj) {
                DB::table('request_children')->insert([
                    'request_id'         => $subRequest->id,
                    'child_id'           => $childObj->id,
                    'pickup_address_id'  => $address->id,
                    'dropoff_address_id' => $address->id,
                    'home_lat'           => $address->lat ?? 32.89000000,
                    'home_lng'           => $address->lng ?? 13.17000000,
                    'home_label'         => $address->label ?? 'ظ…ظ†ط²ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±',
                    'school_lat'         => $school->lat ?? 32.89000000,
                    'school_lng'         => $school->lng ?? 13.17000000,
                    'school_label'       => $school->name,
                    'price_per_child'    => 300.00
                ]);
            }

            // 3. ط¥ط°ط§ ظƒط§ظ† ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ ظ…ظ‚طھط±ظ†ط§ظ‹ ط¨ط¹ظ‚ط¯ ظ†ط´ط· (STATUS_ACCEPTED)
            if ($reqStatus === SubscriptionRequest::STATUS_ACCEPTED) {
                $contractNum = 'CNT-2026-SUB-' . $counter;
                $contract = Contract::create([
                    'subscription_request_id' => $subRequest->id,
                    'parent_id'               => $user->id,
                    'driver_id'               => $driver->user_id ?? $driver->id,
                    'contract_number'         => $contractNum,
                    'subscription_type' => 'multi_day',
                    'direction'               => 'two_way',
                    'timing'                  => 'morning',
                    'pickup_time'             => '07:00:00',
                    'dropoff_time'            => '14:00:00',
                    'max_waiting_time'        => 15,
                    'start_date'              => Carbon::today()->toDateString(),
                    'end_date'                => Carbon::today()->addDays(30)->toDateString(),
                    'days_count'              => 22,
                    'total_price'             => count($createdChildren) * 300.00,
                    'status'                  => 'active',
                    'signed_at'               => now()
                ]);

                foreach ($createdChildren as $childObj) {
                    ActiveSubscription::create([
                        'contract_id'   => $contract->id,
                        'child_id'      => $childObj->id,
                        'driver_id'     => $driver->id,
                        'parent_id'     => $user->id,
                        'pickup_lat'    => $address->lat ?? 32.89000000,
                        'pickup_lng'    => $address->lng ?? 13.17000000,
                        'pickup_label'  => $address->label ?? 'ظ…ظ†ط²ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±',
                        'dropoff_lat'   => $school->lat ?? 32.89000000,
                        'dropoff_lng'   => $school->lng ?? 13.17000000,
                        'dropoff_label' => $school->name,
                        'pickup_time'   => '07:00:00',
                        'dropoff_time'  => '14:00:00',
                        'status'        => 'active'
                    ]);
                }

                // ظپط§طھظˆط±ط© ظ…ط¯ظپظˆط¹ط©
                Invoice::create([
                    'contract_id'     => $contract->id,
                    'parent_id'       => $user->id,
                    'driver_id'       => $driver->id,
                    'invoice_number'  => 'INV-2026-SUB-' . $counter,
                    'amount'          => count($createdChildren) * 300.00,
                    'status'          => 'paid',
                    'type'            => 'monthly',
                    'due_date'        => Carbon::today()->addDays(5)->toDateString(),
                    'paid_at'         => now()
                ]);

                // ط¥ط¶ط§ظپط© طھظ‚ظٹظٹظ… ظ„ظ„ط³ط§ط¦ظ‚ (parent_id ظ‡ظ†ط§ ظٹط´ظٹط± ظ„ط¬ط¯ظˆظ„ parents.id)
                DriverReview::create([
                    'parent_id'   => $parentModel->id,
                    'driver_id'   => $driver->id,
                    'contract_id' => $contract->id,
                    'rating'      => rand(4, 5),
                    'comment'     => 'ط®ط¯ظ…ط© طھطھط¨ط¹ ظˆط§ط´طھط±ط§ظƒ ظ…ظ…طھط§ط²ط© ظˆط³ط§ط¦ظ‚ ط®ظ„ظˆظ‚ ط¬ط¯ط§ظ‹.',
                    'status'      => 'active'
                ]);

                // ط´ظƒظˆظ‰ طھط¬ط±ظٹط¨ظٹط© ظˆط§ظ‚ط¹ظٹط© ظ„ط¨ط¹ط¶ ط§ظ„ط­ط§ظ„ط§طھ
                if ($index === 1) {
                    Complaint::create([
                        'submitted_by'   => $parentModel->id,
                        'against_type'   => 'DRIVER',
                        'against_id'     => $driver->id,
                        'driver_id'      => $driver->id,
                        'description'    => 'طھط£ط®ط± ط§ظ„ط³ط§ط¦ظ‚ 10 ط¯ظ‚ط§ط¦ظ‚ ط¹ظ† ظ…ظˆط¹ط¯ ط§ظ„ط§ط³طھظ„ط§ظ… طµط¨ط§ط­ ط§ظ„ظٹظˆظ….',
                        'status'         => 'pending',
                        'action_taken'   => 'none',
                        'action_details' => null
                    ]);
                }

                // ط؛ظٹط§ط¨ طھط¬ط±ظٹط¨ظٹ ظ„ط£ط­ط¯ ط§ظ„ط£ط·ظپط§ظ„
                if ($index === 2 && !empty($createdChildren)) {
                    AbsenceLog::create([
                        'child_id'     => $createdChildren[0]->id,
                        'absence_date' => Carbon::tomorrow()->toDateString(),
                        'absence_type' => 'both'
                    ]);
                }
            }

            echo "âœ… طھظ… ط±ط¨ط· ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ({$user->full_name}) ط¨ط§ظ„ط¹ط¯ظٹط¯ ظ…ظ† ط§ظ„ط£ط·ظپط§ظ„ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط¨ظ†ط¬ط§ط­!\n";
        }

        echo "ًںژ‰ ط§ظƒطھظ…ظ„ ط²ط±ط¹ ط§ظ„ط£ط·ظپط§ظ„ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظ„ط¬ظ…ظٹط¹ ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ط§ظ„ط­ط§ظ„ظٹط© ط¨ظ†ط¬ط§ط­ طھط§ظ…!\n";
    }
}
