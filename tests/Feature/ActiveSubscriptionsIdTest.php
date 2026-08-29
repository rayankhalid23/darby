<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\SubscriptionRequest;

class ActiveSubscriptionsIdTest extends TestCase
{
    use DatabaseTransactions;

    public function test_driver_and_parent_active_subscriptions_contain_subscription_id(): void
    {
        $driverUser = User::where('email', 'driver1@darby.ly')->first() ?? User::where('role_id', 2)->first();
        $parentUser = User::where('email', 'parent1@darby.ly')->first() ?? User::where('role_id', 3)->first();

        // 1. Driver Active Subscriptions
        $driverResponse = $this->actingAs($driverUser)->getJson('/api/driver/active-subscriptions');
        $driverResponse->assertStatus(200);
        $driverData = $driverResponse->json('data');
        $this->assertNotEmpty($driverData);
        $firstDriverSub = $driverData[0];
        $this->assertArrayHasKey('id', $firstDriverSub);
        $this->assertArrayHasKey('subscription_id', $firstDriverSub);
        $this->assertArrayHasKey('subscription_request_id', $firstDriverSub);
        $this->assertArrayHasKey('child', $firstDriverSub);
        $this->assertArrayHasKey('id', $firstDriverSub['child']);

        // 2. Parent Active Subscriptions
        $parentResponse = $this->actingAs($parentUser)->getJson('/api/parent/active-subscriptions');
        $parentResponse->assertStatus(200);
        $parentData = $parentResponse->json('data');
        $this->assertNotEmpty($parentData);
        $firstParentSub = $parentData[0];
        $this->assertArrayHasKey('id', $firstParentSub);
        $this->assertArrayHasKey('subscription_id', $firstParentSub);
        $this->assertArrayHasKey('subscription_request_id', $firstParentSub);
        $this->assertArrayHasKey('child', $firstParentSub);
        $this->assertArrayHasKey('id', $firstParentSub['child']);
    }
}
