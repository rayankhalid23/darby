<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\DriverReview;

/**
 * اختبار وحدة "تقييمات السائقين" (Admin + Parent) بعد إصلاح:
 * 1) allReviews()/index() كانا يرجعان شكلين مختلفين لنفس البيانات — تم توحيدهما.
 * 2) DELETE /api/parent/driver-reviews/{id} كان بلا أي تحقق ملكية — أي ولي أمر
 *    يقدر يحذف تقييم أي ولي أمر آخر نهائياً.
 * 3) StoreDriverReviewRequest كان يتحقق من عدم تكرار التقييم بمقارنة auth()->id()
 *    (users.id) بعمود parent_id الذي يخزّن فعلياً parents.id — فلا يكتشف التكرار أبداً.
 *
 * ملاحظة: كانت هذه الحالات ضمن ComplaintsAndDriverReviewsTest قبل إزالة منطق
 * الشكاوى بالكامل من الكود الحي (جدول complaints بقي في القاعدة كأرشيف صامت
 * بدون أي Route أو Model يصل إليه).
 */
class DriverReviewsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->adminUser = User::create([
            'full_name'    => 'مدير المراجعات',
            'email'        => 'admin.cx.' . uniqid() . '@darby.test',
            'phone_number' => '090' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 1,
            'is_active'    => 1,
        ]);
        // موديل Admin بلا أعمدة created_at/updated_at في قاعدة البيانات، لذا نُدرج مباشرة
        DB::table('admins')->insert([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق المراجعات',
            'email'        => 'driver.cx.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر المراجعات',
            'email'        => 'parent.cx.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);
    }

    // =========================================================
    // Driver Reviews
    // =========================================================

    public function test_parent_can_submit_driver_review(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/driver-reviews', [
            'driver_id' => $this->driver->id,
            'rating'    => 5,
            'comment'   => 'سائق ممتاز وملتزم بالمواعيد.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.rating', 5);
        $response->assertJsonPath('data.driver.id', $this->driver->id);

        $this->assertDatabaseHas('driver_reviews', [
            'driver_id' => $this->driver->id,
            'parent_id' => $this->parentUser->id,
        ]);
    }

    public function test_parent_cannot_submit_duplicate_review_for_same_driver(): void
    {
        DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 4,
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/driver-reviews', [
            'driver_id' => $this->driver->id,
            'rating'    => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['driver_id']);
    }

    public function test_parent_can_update_own_review(): void
    {
        $review = DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 3,
            'comment'   => 'مقبول',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->parentUser)
            ->putJson("/api/parent/driver-reviews/{$review->id}", ['rating' => 4, 'comment' => 'تحسّن الأداء']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rating', 4);
    }

    public function test_parent_can_delete_own_review(): void
    {
        $review = DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 2,
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->parentUser)->deleteJson("/api/parent/driver-reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('driver_reviews', ['id' => $review->id]);
    }

    public function test_parent_cannot_delete_another_parents_review(): void
    {
        $otherParentUser = User::create([
            'full_name'    => 'ولي أمر آخر للمراجعات',
            'email'        => 'other.cx.' . uniqid() . '@darby.test',
            'phone_number' => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);
        $otherParent = ParentModel::create(['user_id' => $otherParentUser->id, 'is_trusted' => 1]);

        $review = DriverReview::create([
            'parent_id' => $otherParentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 5,
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->parentUser)->deleteJson("/api/parent/driver-reviews/{$review->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('driver_reviews', ['id' => $review->id]);
    }

    // =========================================================
    // Admin — Driver Reviews: شكل استجابة موحّد بين all و driver/{id}
    // =========================================================

    public function test_admin_all_reviews_returns_unified_shape_with_driver_and_parent_names(): void
    {
        DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 5,
            'comment'   => 'ممتاز',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/driver-reviews/all');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status', 'data' => [['id', 'driver_id', 'rating', 'comment', 'created_at', 'parent', 'driver']],
            'pagination' => ['current_page', 'last_page', 'total', 'per_page'],
        ]);

        $item = collect($response->json('data'))->firstWhere('driver_id', $this->driver->id);
        $this->assertEquals($this->parentUser->full_name, $item['parent']['full_name']);
        $this->assertEquals($this->driverUser->full_name, $item['driver']['name']);
    }

    public function test_admin_driver_reviews_by_driver_matches_same_shape(): void
    {
        DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 4,
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/driver-reviews/driver/{$this->driver->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data' => [['id', 'driver_id', 'rating', 'parent', 'driver']]]);
        $response->assertJsonPath('data.0.driver.name', $this->driverUser->full_name);
    }

    public function test_admin_can_force_delete_a_review(): void
    {
        $review = DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 1,
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/api/admin/driver-reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('driver_reviews', ['id' => $review->id]);
    }
}
