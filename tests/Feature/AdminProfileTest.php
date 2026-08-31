<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 🧪 اختبارات الملف الشخصي لمدير النظام والمشرف
 *
 * تغطي: عرض البروفايل / تعديل البروفايل / تغيير كلمة المرور / تغيير البريد
 * لكلا الدورين (role_id = 1 مدير النظام، role_id = 2 مشرف).
 */
class AdminProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    /**
     * إنشاء حساب مشرف/مدير مرتبط بسجل في جدول admins
     */
    private function makeAccount(int $roleId, array $overrides = []): Admin
    {
        $user = User::create(array_merge([
            'full_name'     => ($roleId === 1 ? 'مدير نظام' : 'مشرف') . ' اختبار ' . uniqid(),
            'email'         => 'prof.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => $roleId,
            'is_active'     => 1,
        ], $overrides));

        return Admin::create([
            'user_id'    => $user->id,
            'created_by' => $user->id,
        ]);
    }

    /** الأدوار التي يجب أن تعمل بنفس الطريقة */
    public static function roleProvider(): array
    {
        return [
            'مدير النظام' => [1],
            'مشرف'        => [2],
        ];
    }

    // =====================================================================
    // 1️⃣ عرض البروفايل — GET /api/admin/profile
    // =====================================================================

    #[DataProvider('roleProvider')]
    public function test_can_view_own_profile(int $roleId): void
    {
        $admin = $this->makeAccount($roleId);

        $response = $this->actingAs($admin->user)->getJson('/api/admin/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم جلب بيانات الملف الشخصي بنجاح.');

        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id', 'user_id', 'full_name', 'email', 'phone_number',
                'avatar_url', 'is_active', 'role_id', 'role_name',
                'created_by', 'creator_name', 'created_at', 'last_login_at',
                'email_change_pending', 'pending_new_email',
            ],
        ]);

        $response->assertJsonPath('data.id', $admin->id);
        $response->assertJsonPath('data.user_id', $admin->user_id);
        $response->assertJsonPath('data.email', $admin->user->email);
        $response->assertJsonPath('data.role_id', $roleId);
        // الاسم المعروض للدور يُقرأ من جدول roles لا يُثبَّت نصياً:
        // الأسماء تُعدَّل من لوحة الإدارة وتختلف بين البيئات، وتثبيتها هنا يجعل
        // الاختبار يفشل لسبب لا علاقة له بصحة الـ API.
        $expectedRoleName = DB::table('roles')->where('id', $roleId)->value('display_name');
        $response->assertJsonPath('data.role_name', $expectedRoleName);
        $response->assertJsonPath('data.email_change_pending', false);
    }

    public function test_profile_returns_404_for_user_without_admin_record(): void
    {
        // مستخدم بدور مشرف لكن بلا سجل في جدول admins
        $orphan = User::create([
            'full_name'     => 'مستخدم بلا سجل مشرف',
            'email'         => 'orphan.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->actingAs($orphan)->getJson('/api/admin/profile')
            ->assertStatus(404)
            ->assertJsonPath('message', 'حسابك غير مسجل ضمن المشرفين.');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/admin/profile')->assertStatus(401);
    }

    // =====================================================================
    // 2️⃣ تعديل البروفايل — POST /api/admin/profile
    // =====================================================================

    #[DataProvider('roleProvider')]
    public function test_can_update_own_name_and_phone(int $roleId): void
    {
        $admin    = $this->makeAccount($roleId);
        $newPhone = '09' . rand(10000000, 99999999);

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'full_name'    => 'اسمي الجديد المعدل',
            'phone_number' => $newPhone,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'تم تحديث ملفك الشخصي بنجاح.');
        $response->assertJsonPath('data.full_name', 'اسمي الجديد المعدل');
        $response->assertJsonPath('data.phone_number', $newPhone);
        $response->assertJsonPath('email_verification', null);

        $this->assertDatabaseHas('users', [
            'id'           => $admin->user_id,
            'full_name'    => 'اسمي الجديد المعدل',
            'phone_number' => $newPhone,
        ]);
    }

    public function test_partial_update_leaves_other_fields_intact(): void
    {
        $admin = $this->makeAccount(2);
        $email = $admin->user->email;
        $phone = $admin->user->phone_number;

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'full_name' => 'تعديل الاسم فقط هنا',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', $email);
        $response->assertJsonPath('data.phone_number', $phone);
    }

    public function test_can_update_own_avatar(): void
    {
        Storage::fake('public');
        $admin = $this->makeAccount(2);

        $response = $this->actingAs($admin->user)->post(
            '/api/admin/profile',
            ['avatar' => UploadedFile::fake()->image('me.jpg')],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('/api/admin/avatars/', $avatarUrl);
        $this->assertCount(1, Storage::disk('public')->allFiles('uploads/admins/avatars'));
    }

    public function test_profile_update_rejects_invalid_data(): void
    {
        $admin = $this->makeAccount(2);

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'full_name'    => 'اسم',
            'phone_number' => '0512345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonValidationErrors(['full_name', 'phone_number']);
    }

    public function test_cannot_take_phone_or_email_of_another_user(): void
    {
        $me    = $this->makeAccount(2);
        $other = $this->makeAccount(2);

        $response = $this->actingAs($me->user)->postJson('/api/admin/profile', [
            'email'        => $other->user->email,
            'phone_number' => $other->user->phone_number,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'phone_number']);
    }

    public function test_resending_own_unchanged_email_is_not_an_error(): void
    {
        $admin = $this->makeAccount(2);

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'email' => $admin->user->email,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('email_verification', null);
        $response->assertJsonPath('message', 'تم تحديث ملفك الشخصي بنجاح.');
    }

    public function test_user_cannot_change_own_active_status(): void
    {
        $admin = $this->makeAccount(2);

        $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'is_active' => false,
        ])->assertStatus(200);

        // يبقى مفعّلاً — الحقل غير مسموح به في بروفايل المستخدم نفسه
        $this->assertDatabaseHas('users', [
            'id'        => $admin->user_id,
            'is_active' => 1,
        ]);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->postJson('/api/admin/profile', ['full_name' => 'اسم ثلاثي جديد'])
            ->assertStatus(401);
    }

    // =====================================================================
    // 3️⃣ تغيير كلمة المرور
    // =====================================================================

    #[DataProvider('roleProvider')]
    public function test_can_change_own_password_with_correct_current_password(int $roleId): void
    {
        $admin   = $this->makeAccount($roleId);
        $oldHash = $admin->user->password_hash;

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'current_password'      => 'password123',
            'password'              => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $response->assertStatus(200);

        $fresh = User::find($admin->user_id);
        $this->assertNotEquals($oldHash, $fresh->password_hash);
        $this->assertTrue(Hash::check('newPassword456', $fresh->password_hash));
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $admin   = $this->makeAccount(2);
        $oldHash = $admin->user->password_hash;

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'current_password'      => 'wrongPassword',
            'password'              => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
        $this->assertEquals($oldHash, User::find($admin->user_id)->password_hash);
    }

    public function test_password_change_requires_current_password(): void
    {
        $admin = $this->makeAccount(2);

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'password'              => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_requires_matching_confirmation(): void
    {
        $admin = $this->makeAccount(2);

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'current_password'      => 'password123',
            'password'              => 'newPassword456',
            'password_confirmation' => 'different789',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // =====================================================================
    // 4️⃣ تغيير البريد الإلكتروني من البروفايل
    // =====================================================================

    #[DataProvider('roleProvider')]
    public function test_changing_own_email_does_not_apply_immediately(int $roleId): void
    {
        $admin    = $this->makeAccount($roleId);
        $oldEmail = $admin->user->email;
        $newEmail = 'mynew.' . uniqid() . '@darby.test';

        $response = $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'email' => $newEmail,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('أرسلنا رابط تأكيد', $response->json('message'));

        $response->assertJsonPath('email_verification.status', 'pending');
        $response->assertJsonPath('email_verification.new_email', $newEmail);
        $response->assertJsonPath('data.email', $oldEmail);
        $response->assertJsonPath('data.email_change_pending', true);
        $response->assertJsonPath('data.pending_new_email', $newEmail);

        // البريد الفعلي لم يتغير في قاعدة البيانات
        $this->assertDatabaseHas('users', ['id' => $admin->user_id, 'email' => $oldEmail]);
        $this->assertDatabaseMissing('users', ['email' => $newEmail]);
    }

    public function test_can_check_own_email_change_status(): void
    {
        $admin    = $this->makeAccount(2);
        $newEmail = 'status.' . uniqid() . '@darby.test';

        $this->actingAs($admin->user)->postJson('/api/admin/profile', ['email' => $newEmail]);

        $response = $this->actingAs($admin->user)
            ->getJson('/api/admin/profile/email-change/status');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.new_email', $newEmail);
    }

    public function test_status_is_expired_when_no_pending_request(): void
    {
        $admin = $this->makeAccount(2);

        $this->actingAs($admin->user)
            ->getJson('/api/admin/profile/email-change/status')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.new_email', null);
    }

    public function test_can_cancel_own_email_change_request(): void
    {
        $admin = $this->makeAccount(2);

        $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'email' => 'cancelme.' . uniqid() . '@darby.test',
        ]);

        $this->actingAs($admin->user)
            ->postJson('/api/admin/profile/email-change/cancel')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'تم إلغاء طلب تغيير البريد الإلكتروني.');

        // اختفى الطلب المعلق تماماً
        $this->actingAs($admin->user)->getJson('/api/admin/profile')
            ->assertJsonPath('data.email_change_pending', false)
            ->assertJsonPath('data.pending_new_email', null);
    }

    public function test_cancel_fails_when_nothing_is_pending(): void
    {
        $admin = $this->makeAccount(2);

        $this->actingAs($admin->user)
            ->postJson('/api/admin/profile/email-change/cancel')
            ->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_can_resend_own_confirmation_link(): void
    {
        $admin    = $this->makeAccount(2);
        $newEmail = 'resend.' . uniqid() . '@darby.test';

        $this->actingAs($admin->user)->postJson('/api/admin/profile', ['email' => $newEmail]);

        $response = $this->actingAs($admin->user)
            ->postJson('/api/admin/profile/email-change/resend');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'تمت إعادة إرسال رابط التأكيد بنجاح.');
        $response->assertJsonPath('email_verification.status', 'pending');
        $response->assertJsonPath('email_verification.new_email', $newEmail);
    }

    public function test_resend_fails_when_nothing_is_pending(): void
    {
        $admin = $this->makeAccount(2);

        $this->actingAs($admin->user)
            ->postJson('/api/admin/profile/email-change/resend')
            ->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_guest_cannot_access_email_change_endpoints(): void
    {
        $this->getJson('/api/admin/profile/email-change/status')->assertStatus(401);
        $this->postJson('/api/admin/profile/email-change/cancel')->assertStatus(401);
        $this->postJson('/api/admin/profile/email-change/resend')->assertStatus(401);
    }

    // =====================================================================
    // 🔄 دورة كاملة
    // =====================================================================

    public function test_full_profile_flow_end_to_end(): void
    {
        Storage::fake('public');
        $admin = $this->makeAccount(1);

        // عرض
        $this->actingAs($admin->user)->getJson('/api/admin/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.role_name', DB::table('roles')->where('id', 1)->value('display_name'));

        // تعديل الاسم والصورة معاً
        $this->actingAs($admin->user)->post('/api/admin/profile', [
            'full_name' => 'مدير النظام المحدث',
            'avatar'    => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonPath('data.full_name', 'مدير النظام المحدث');

        // تغيير كلمة المرور
        $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'current_password'      => 'password123',
            'password'              => 'finalPass999',
            'password_confirmation' => 'finalPass999',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('finalPass999', User::find($admin->user_id)->password_hash));

        // طلب تغيير البريد ثم إلغاؤه
        $this->actingAs($admin->user)->postJson('/api/admin/profile', [
            'email' => 'final.' . uniqid() . '@darby.test',
        ])->assertStatus(200)->assertJsonPath('data.email_change_pending', true);

        $this->actingAs($admin->user)
            ->postJson('/api/admin/profile/email-change/cancel')
            ->assertStatus(200);

        $this->actingAs($admin->user)->getJson('/api/admin/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.email_change_pending', false);
    }
}
