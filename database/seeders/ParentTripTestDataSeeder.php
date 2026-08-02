<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Shared\TripEvent;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\SubscriptionRequest;
use Carbon\Carbon;

class ParentTripTestDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Create the target parent user
        $phone = '091' . rand(1000000, 9999999);
        $user = User::create([
            'full_name' => 'Demo Parent All Statuses',
            'email' => 'demoparent' . rand(1, 1000) . '@test.com',
            'phone_number' => $phone,
            'password_hash' => bcrypt('12345678'),
            'role_id' => 3
        ]);

        $parentModel = ParentModel::create([
            'user_id' => $user->id,
            'is_trusted' => 1
        ]);

        // 2. Create School, Driver, Vehicle, Route
        $school = School::create([
            'name' => 'Demo School',
            'lat' => 32.880,
            'lng' => 13.190,
        ]);

        $driverUser = User::create([
            'full_name' => 'Demo Driver',
            'email' => 'demodriver' . rand(1, 1000) . '@test.com',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password'),
            'role_id' => 2
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'current_lat' => 32.885,
            'current_lng' => 13.185,
            'status' => 'Approved',
            'shift' => 1, // Morning
            'subscription_type' => 'monthly',
            'national_id' => rand(100000000000, 999999999999),
            'license_number' => 'LIC-' . rand(100000, 999999),
            'license_expiry' => '2030-01-01',
        ]);

        $vehicle = Vehicle::create([
            'driver_id' => $driver->id,
            'brand' => 'Toyota',
            'model' => 'Hiace',
            'year' => '2022',
            'capacity_manual' => 10,
            'plate_number' => rand(10000, 99999),
            'color' => 'White',
        ]);

        $route = Route::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_name' => 'Demo Route',
            'route_type' => 'Morning',
            'start_time' => '07:00:00',
            'status' => 'Active'
        ]);

        $subReq = SubscriptionRequest::create([
            'parent_id' => $parentModel->id,
            'driver_id' => $driver->id,
            'school_id' => $school->id,
            'subscription_type' => 'monthly',
            'direction' => 'both',
            'timing' => 'Morning',
            'children_count' => 1,
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'max_waiting_time' => 5,
            'status' => 'accepted',
        ]);

        $contract = Contract::create([
            'subscription_request_id' => $subReq->id,
            'parent_id' => $user->id,
            'driver_id' => $driverUser->id,
            'contract_number' => Contract::generateContractNumber(),
            'subscription_type' => 'monthly',
            'direction' => 'both',
            'timing' => 'Morning',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'total_price' => 150.00,
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'max_waiting_time' => 5,
            'status' => 'active'
        ]);

        // Define different trip statuses we want to mock
        $statuses = [
            ['status' => 'started', 'action_type' => 'picked_up'],
            ['status' => 'completed', 'action_type' => 'dropped_off'],
            ['status' => 'cancelled', 'action_type' => 'absent']
        ];

        foreach ($statuses as $index => $st) {
            $child = Child::create([
                'parent_id' => $parentModel->id,
                'school_id' => $school->id,
                'full_name' => 'Child ' . $st['status'],
                'birth_date' => '2015-01-01',
                'gender' => 'male',
                'grade' => '1',
            ]);

            $subscription = ActiveSubscription::create([
                'contract_id' => $contract->id,
                'child_id' => $child->id,
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'parent_id' => $user->id,
                'pickup_lat' => 32.890,
                'pickup_lng' => 13.180,
                'dropoff_lat' => 32.880,
                'dropoff_lng' => 13.190,
                'status' => 'active',
            ]);

            $trip = Trip::create([
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'trip_type' => 'Morning',
                'status' => $st['status'],
                'scheduled_at' => Carbon::now()->subDays($index),
                'started_at' => $st['status'] !== 'cancelled' ? Carbon::now()->subDays($index) : null,
                'completed_at' => $st['status'] === 'completed' ? Carbon::now()->subDays($index)->addHours(1) : null,
                'scheduled_start_time' => Carbon::now()->subDays($index),
                'actual_start_time' => $st['status'] !== 'cancelled' ? Carbon::now()->subDays($index) : null,
                'trip_date' => Carbon::today()->subDays($index)->toDateString(),
            ]);

            TripEvent::create([
                'trip_id' => $trip->id,
                'child_id' => $child->id,
                'subscription_id' => $subscription->id,
                'action_type' => $st['action_type'],
                'scanned_at' => Carbon::now()->subDays($index),
                'location_lat' => 32.880,
                'location_lng' => 13.190,
                'trip_cost' => 15.00
            ]);
        }

        $this->command->info('Test data seeded successfully.');
        $this->command->info('Parent Phone Number: ' . $phone);
        $this->command->info('Parent Password: 12345678');
    }
}
