<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\SubscriptionRequest;
use Illuminate\Support\Facades\DB;

class DriverSubscriptionRequestsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverUser = User::where('email', 'driver1@darby.ly')->first() ?? User::where('role_id', 2)->first();
        $this->driver = $this->driverUser->driver;

        $this->parentUser = User::where('email', 'parent1@darby.ly')->first() ?? User::where('role_id', 3)->first();
        $this->parent = $this->parentUser->parent;
    }

    public function test_driver_can_fetch_requests_via_all_aliases(): void
    {
        // 1. /api/driver/requests
        $response1 = $this->actingAs($this->driverUser)->getJson('/api/driver/requests');
        $response1->assertStatus(200);
        $response1->assertJsonPath('status', true);
        $response1->assertJsonStructure(['data', 'links', 'meta', 'status', 'success']);

        // 2. /api/driver/subscription-requests
        $response2 = $this->actingAs($this->driverUser)->getJson('/api/driver/subscription-requests');
        $response2->assertStatus(200);
        $response2->assertJsonPath('status', true);

        // 3. /api/v1/driver/requests
        $response3 = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/requests');
        $response3->assertStatus(200);
        $response3->assertJsonPath('status', true);

        // 4. /api/v1/driver/subscription-requests
        $response4 = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/subscription-requests');
        $response4->assertStatus(200);
        $response4->assertJsonPath('status', true);
    }

    public function test_driver_can_view_request_details_and_active_subscriptions(): void
    {
        $request = SubscriptionRequest::where('driver_id', $this->driver->id)->first();
        if ($request) {
            $resDetails = $this->actingAs($this->driverUser)->getJson("/api/driver/requests/{$request->id}");
            $resDetails->assertStatus(200);
            $resDetails->assertJsonPath('status', true);

            $resDetailsV1 = $this->actingAs($this->driverUser)->getJson("/api/v1/driver/subscription-requests/{$request->id}");
            $resDetailsV1->assertStatus(200);
            $resDetailsV1->assertJsonPath('status', true);
        }

        $resActive = $this->actingAs($this->driverUser)->getJson('/api/driver/active-subscriptions');
        $resActive->assertStatus(200);
        $resActive->assertJsonPath('status', true);

        $resActiveV1 = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/active-subscriptions');
        $resActiveV1->assertStatus(200);
        $resActiveV1->assertJsonPath('status', true);
    }
}
