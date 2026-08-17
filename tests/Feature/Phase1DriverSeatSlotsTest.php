<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Tests\TestCase;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;

class Phase1DriverSeatSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_shifts_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('drivers', [
            'morning_go',
            'morning_return',
            'afternoon_go',
            'afternoon_return'
        ]));
    }

    private function makeDriverUser(): User
    {
        return User::create([
            'full_name'     => 'سائق اختبار الفترات',
            'email'         => 'seatslot.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
    }

    public function test_creates_driver_seat_slot_and_calculates_available_seats(): void
    {
        $driver = Driver::create([
            'user_id' => $this->makeDriverUser()->id,
            'status' => 'Approved',
        ]);

        $slot = DriverSeatSlot::create([
            'driver_id' => $driver->id,
            'slot' => 'morning_go',
            'total_seats' => 4,
            'reserved_seats' => 1,
        ]);

        $this->assertEquals(3, $slot->available_seats);
    }

    public function test_enforces_unique_constraint_on_driver_id_and_slot(): void
    {
        $driver = Driver::create([
            'user_id' => $this->makeDriverUser()->id,
            'status' => 'Approved',
        ]);

        DriverSeatSlot::create([
            'driver_id' => $driver->id,
            'slot' => 'morning_go',
            'total_seats' => 4,
            'reserved_seats' => 0,
        ]);

        $this->expectException(QueryException::class);

        DriverSeatSlot::create([
            'driver_id' => $driver->id,
            'slot' => 'morning_go',
            'total_seats' => 4,
            'reserved_seats' => 0,
        ]);
    }
}