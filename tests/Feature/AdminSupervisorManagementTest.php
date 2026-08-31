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
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * 🧪 اختبارات شاملة لإدارة المشرفين في لوحة التحكم
 *
 * تغطي: إضافة مشرف / عرض كل المشرفين / عرض مشرف معين / تعديل مشرف / حذف مشرف
 */
class AdminSupervisorManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Admin $adminProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // منع إرسال أي بريد حقيقي أثناء الاختبار
        Mail::fake();

        // مدير النظام الذي سينفذ كل العمليات
        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام للاختبار',
            'email'         => 'root.admin.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        $this->adminProfile = Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    /**
     * إنشاء مشرف جاهز في قاعدة البيانات مباشرة (بدون المرور على الـ API)
     */
    private function makeSupervisor(array $overrides = []): Admin
    {
        $user = User::create(array_merge([
            'full_name'     => 'مشرف تجريبي رقم ' . uniqid(),
            'email'         => 'sup.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ], $overrides));

        return Admin::create([
            'user_id'    => $user->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    // =====================================================================
    // 1️⃣ إضافة مشرف — POST /api/admin/admins
    // =====================================================================

    public function test_admin_can_create_new_supervisor(): void
    {
        $email = 'new.supervisor.' . uniqid() . '@darby.test';
        $phone = '093' . rand(1000000, 9999999);

        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'محمد علي الطرابلسي',
            'email'        => $email,
            'phone_number' => $phone,
            'password'     => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم إضافة المشرف بنجاح.');
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id', 'user_id', 'full_name', 'email', 'phone_number',
                'avatar_url', 'is_active', 'role_id', 'role_name',
                'created_by', 'creator_name', 'created_at', 'last_login_at',
            ],
        ]);

        $response->assertJsonPath('data.full_name', 'محمد علي الطرابلسي');
        $response->assertJsonPath('data.email', $email);
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.role_id', 2);
        $response->assertJsonPath('data.role_name', DB::table('roles')->where('id', 2)->value('display_name'));

        // التحقق من الحفظ الفعلي في قاعدة البيانات بالدورين الصحيحين
        $this->assertDatabaseHas('users', [
            'email'   => $email,
            'role_id' => 2,
        ]);

        $createdUser = User::where('email', $email)->first();
        $this->assertDatabaseHas('admins', [
            'user_id'    => $createdUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        // كلمة المرور يجب أن تُحفظ مشفرة وليست نصاً صريحاً
        $this->assertNotEquals('secret123', $createdUser->password_hash);
        $this->assertTrue(Hash::check('secret123', $createdUser->password_hash));
    }

    public function test_supervisor_creation_generates_password_when_not_provided(): void
    {
        $email = 'auto.pass.' . uniqid() . '@darby.test';

        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'سالم فرج المرزوقي',
            'email'        => $email,
            'phone_number' => '094' . rand(1000000, 9999999),
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty(User::where('email', $email)->first()->password_hash);
    }

    public function test_supervisor_creation_can_upload_avatar(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser)->post('/api/admin/admins', [
            'full_name'    => 'نوري عبدالسلام الفيتوري',
            'email'        => 'avatar.sup.' . uniqid() . '@darby.test',
            'phone_number' => '095' . rand(1000000, 9999999),
            'avatar'       => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.avatar_url'));
        $this->assertCount(1, Storage::disk('public')->allFiles('uploads/admins/avatars'));
    }

    public function test_supervisor_creation_rejects_invalid_input(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'محمد',            // اسم غير ثلاثي
            'email'        => 'not-an-email',    // صيغة خاطئة
            'phone_number' => '0712345',         // لا يبدأ بـ 09 وأقل من 10 أرقام
            'password'     => '123',             // أقل من 6 خانات
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonValidationErrors(['full_name', 'email', 'phone_number', 'password']);
    }

    public function test_supervisor_creation_rejects_duplicate_email_and_phone(): void
    {
        $existing = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'اسم ثلاثي جديد تماماً',
            'email'        => $existing->user->email,
            'phone_number' => $existing->user->phone_number,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'phone_number']);
    }

    public function test_guest_cannot_create_supervisor(): void
    {
        $response = $this->postJson('/api/admin/admins', [
            'full_name'    => 'زائر غير مصرح له',
            'email'        => 'guest.' . uniqid() . '@darby.test',
            'phone_number' => '0961234567',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    // =====================================================================
    // 2️⃣ عرض كل المشرفين — GET /api/admin/admins
    // =====================================================================

    public function test_admin_can_list_all_supervisors(): void
    {
        $first  = $this->makeSupervisor();
        $second = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/admins');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم جلب قائمة المشرفين بنجاح.');

        // هيكل الترقيم القياسي الذي يعتمد عليه الفرونت
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id', 'user_id', 'full_name', 'email', 'phone_number',
                        'avatar_url', 'is_active', 'role_id', 'role_name',
                        'created_by', 'creator_name', 'created_at', 'last_login_at',
                    ],
                ],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);

        // المشرفون المنشأون حديثاً يجب أن يظهروا في المقدمة (ترتيب تنازلي)
        $ids = array_column($response->json('data.data'), 'id');
        $this->assertContains($second->id, $ids);
        $this->assertEquals($second->id, $ids[0]);
        $this->assertGreaterThan(array_search($second->id, $ids), array_search($first->id, $ids));
    }

    public function test_supervisors_list_supports_per_page_and_search(): void
    {
        $target = $this->makeSupervisor(['full_name' => 'بحث فريد جداً ' . uniqid()]);
        $this->makeSupervisor();
        $this->makeSupervisor();

        // الترقيم
        $paged = $this->actingAs($this->adminUser)->getJson('/api/admin/admins?per_page=2');
        $paged->assertStatus(200);
        $paged->assertJsonPath('data.meta.per_page', 2);
        $this->assertCount(2, $paged->json('data.data'));

        // البحث بالاسم
        $searched = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins?search=' . urlencode($target->user->full_name));

        $searched->assertStatus(200);
        $this->assertCount(1, $searched->json('data.data'));
        $searched->assertJsonPath('data.data.0.id', $target->id);

        // البحث بالبريد الإلكتروني
        $byEmail = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins?search=' . urlencode($target->user->email));

        $byEmail->assertStatus(200);
        $byEmail->assertJsonPath('data.data.0.id', $target->id);
    }

    public function test_guest_cannot_list_supervisors(): void
    {
        $this->getJson('/api/admin/admins')->assertStatus(401);
    }

    // =====================================================================
    // 3️⃣ عرض بيانات مشرف معين — GET /api/admin/admins/{id}
    // =====================================================================

    public function test_admin_can_show_single_supervisor(): void
    {
        $supervisor = $this->makeSupervisor(['full_name' => 'صالح أحمد البرعصي']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم جلب بيانات المشرف.');
        $response->assertJsonPath('data.id', $supervisor->id);
        $response->assertJsonPath('data.user_id', $supervisor->user_id);
        $response->assertJsonPath('data.full_name', 'صالح أحمد البرعصي');
        $response->assertJsonPath('data.email', $supervisor->user->email);
        $response->assertJsonPath('data.role_name', DB::table('roles')->where('id', 2)->value('display_name'));
        $response->assertJsonPath('data.created_by', $this->adminUser->id);
        $response->assertJsonPath('data.creator_name', $this->adminUser->full_name);
    }

    public function test_show_returns_404_for_missing_supervisor(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/admins/99999999');

        $response->assertStatus(404);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'عذراً، المشرف غير موجود.');
    }

    public function test_guest_cannot_show_supervisor(): void
    {
        $supervisor = $this->makeSupervisor();
        $this->getJson('/api/admin/admins/' . $supervisor->id)->assertStatus(401);
    }

    // =====================================================================
    // 4️⃣ تعديل مشرف — POST /api/admin/admins/{id}
    // =====================================================================

    public function test_admin_can_update_supervisor_basic_fields(): void
    {
        $supervisor = $this->makeSupervisor();
        $newPhone   = '096' . rand(1000000, 9999999);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'full_name'    => 'الاسم المعدل الجديد',
                'phone_number' => $newPhone,
                'is_active'    => false,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم تحديث بيانات المشرف بنجاح.');
        $response->assertJsonPath('data.full_name', 'الاسم المعدل الجديد');
        $response->assertJsonPath('data.phone_number', $newPhone);
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id'           => $supervisor->user_id,
            'full_name'    => 'الاسم المعدل الجديد',
            'phone_number' => $newPhone,
            'is_active'    => 0,
        ]);
    }

    public function test_partial_update_keeps_other_fields_untouched(): void
    {
        $supervisor   = $this->makeSupervisor();
        $originalMail = $supervisor->user->email;
        $originalPhone= $supervisor->user->phone_number;

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'full_name' => 'اسم ثلاثي معدل فقط',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', $originalMail);
        $response->assertJsonPath('data.phone_number', $originalPhone);
    }

    public function test_update_password_is_hashed_and_changed(): void
    {
        $supervisor = $this->makeSupervisor();
        $oldHash    = $supervisor->user->password_hash;

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'password' => 'newSecret456',
            ]);

        $response->assertStatus(200);

        $freshUser = User::find($supervisor->user_id);
        $this->assertNotEquals($oldHash, $freshUser->password_hash);
        $this->assertTrue(Hash::check('newSecret456', $freshUser->password_hash));
    }

    public function test_update_email_requires_confirmation_and_does_not_change_immediately(): void
    {
        $supervisor   = $this->makeSupervisor();
        $originalMail = $supervisor->user->email;
        $newMail      = 'changed.' . uniqid() . '@darby.test';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'email' => $newMail,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertStringContainsString('أرسلنا رابط تأكيد', $response->json('message'));

        // كائن email_verification الذي تعتمد عليه الواجهة لفتح نافذة الانتظار
        $response->assertJsonStructure(['email_verification' => ['status', 'new_email', 'expires_at']]);
        $response->assertJsonPath('email_verification.status', 'pending');
        $response->assertJsonPath('email_verification.new_email', $newMail);

        // الحقول المعلّقة داخل بيانات المشرف
        $response->assertJsonPath('data.email_change_pending', true);
        $response->assertJsonPath('data.pending_new_email', $newMail);
        $response->assertJsonPath('data.email', $originalMail);

        // البريد القديم يبقى كما هو حتى يضغط المشرف على رابط التأكيد
        $this->assertDatabaseHas('users', [
            'id'    => $supervisor->user_id,
            'email' => $originalMail,
        ]);
        $this->assertDatabaseMissing('users', ['email' => $newMail]);
    }

    public function test_update_without_email_change_returns_null_email_verification(): void
    {
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'full_name' => 'اسم ثلاثي بلا بريد',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('email_verification', null);
        $response->assertJsonPath('data.email_change_pending', false);
        $response->assertJsonPath('data.pending_new_email', null);
    }

    // =====================================================================
    // 📧 دورة تأكيد تغيير البريد الإلكتروني (مطابقة لآلية ولي الأمر)
    // =====================================================================

    /**
     * طلب تغيير بريد والتقاط رابطي القبول والرفض من الكاش
     */
    private function requestEmailChange(Admin $supervisor, ?string $newMail = null): array
    {
        $newMail ??= 'pending.' . uniqid() . '@darby.test';

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, ['email' => $newMail])
            ->assertStatus(200);

        $pending = Cache::get("admin_email_change_{$supervisor->user_id}");
        $this->assertNotNull($pending, 'لم يُسجَّل طلب تغيير البريد في الكاش');

        return [
            'new_email' => $newMail,
            'token'     => $pending['token'],
            'approve'   => URL::temporarySignedRoute('admin.email.approve', now()->addMinutes(30), ['token' => $pending['token']]),
            'reject'    => URL::temporarySignedRoute('admin.email.reject', now()->addMinutes(30), ['token' => $pending['token']]),
        ];
    }

    public function test_email_change_status_is_pending_after_request(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id . '/email-change/status');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.new_email', $req['new_email']);
    }

    public function test_email_change_status_is_expired_when_no_request_exists(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id . '/email-change/status')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.new_email', null);
    }

    public function test_approving_link_actually_changes_the_email(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $approve = $this->getJson($req['approve']);
        $approve->assertStatus(200);
        $approve->assertJsonPath('status', true);
        $approve->assertJsonPath('email_changed', true);
        $approve->assertJsonPath('data.email', $req['new_email']);

        // البريد تغيّر فعلياً وتم توثيقه
        $this->assertDatabaseHas('users', [
            'id'    => $supervisor->user_id,
            'email' => $req['new_email'],
        ]);
        $this->assertNotNull(User::find($supervisor->user_id)->email_verified_at);

        // الحالة صارت verified
        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id . '/email-change/status')
            ->assertJsonPath('data.status', 'verified');
    }

    public function test_approval_link_cannot_be_used_twice(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $this->getJson($req['approve'])->assertStatus(200);
        $this->getJson($req['approve'])->assertStatus(400);
    }

    public function test_approval_link_rejects_tampered_signature(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $this->getJson($req['approve'] . 'TAMPERED')->assertStatus(403);

        // البريد لم يتغير
        $this->assertDatabaseMissing('users', ['email' => $req['new_email']]);
    }

    public function test_rejecting_link_keeps_old_email_and_sets_rejected_status(): void
    {
        $supervisor   = $this->makeSupervisor();
        $originalMail = $supervisor->user->email;
        $req = $this->requestEmailChange($supervisor);

        $this->getJson($req['reject'])
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'id'    => $supervisor->user_id,
            'email' => $originalMail,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id . '/email-change/status')
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_admin_can_cancel_pending_email_change(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/cancel')
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        // الطلب اختفى من الكاش ولم يعد الرابط يعمل
        $this->assertNull(Cache::get("admin_email_change_{$supervisor->user_id}"));
        $this->getJson($req['approve'])->assertStatus(400);
    }

    public function test_cancel_fails_when_there_is_no_pending_request(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/cancel')
            ->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_admin_can_resend_verification_link(): void
    {
        $supervisor = $this->makeSupervisor();
        $req = $this->requestEmailChange($supervisor);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/resend');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('email_verification.status', 'pending');
        $response->assertJsonPath('email_verification.new_email', $req['new_email']);

        // التوكن القديم أُبطل وصدر توكن جديد
        $fresh = Cache::get("admin_email_change_{$supervisor->user_id}");
        $this->assertNotEquals($req['token'], $fresh['token']);
        $this->getJson($req['approve'])->assertStatus(400);
    }

    public function test_resend_fails_when_there_is_no_pending_request(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/resend')
            ->assertStatus(400);
    }

    public function test_new_email_request_invalidates_the_previous_one(): void
    {
        $supervisor = $this->makeSupervisor();
        $first  = $this->requestEmailChange($supervisor);
        $second = $this->requestEmailChange($supervisor);

        // الرابط الأول لم يعد صالحاً، والثاني يعمل
        $this->getJson($first['approve'])->assertStatus(400);
        $this->getJson($second['approve'])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'    => $supervisor->user_id,
            'email' => $second['new_email'],
        ]);
    }

    public function test_email_change_endpoints_require_authentication(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->getJson('/api/admin/admins/' . $supervisor->id . '/email-change/status')->assertStatus(401);
        $this->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/cancel')->assertStatus(401);
        $this->postJson('/api/admin/admins/' . $supervisor->id . '/email-change/resend')->assertStatus(401);
    }

    public function test_email_change_endpoints_return_404_for_missing_supervisor(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/99999999/email-change/status')->assertStatus(404);

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/99999999/email-change/cancel')->assertStatus(404);

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/99999999/email-change/resend')->assertStatus(404);
    }

    public function test_update_allows_resending_same_email_without_unique_error(): void
    {
        $supervisor = $this->makeSupervisor();

        // إرسال نفس البريد الحالي يجب ألا يسبب خطأ "البريد مستخدم مسبقاً"
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'full_name' => $supervisor->user->full_name,
                'email'     => $supervisor->user->email,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'تم تحديث بيانات المشرف بنجاح.');
    }

    public function test_update_can_replace_avatar(): void
    {
        Storage::fake('public');
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)->post(
            '/api/admin/admins/' . $supervisor->id,
            ['avatar' => UploadedFile::fake()->image('new-avatar.png')],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.avatar_url'));
        $this->assertCount(1, Storage::disk('public')->allFiles('uploads/admins/avatars'));
    }

    public function test_update_rejects_invalid_data(): void
    {
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'full_name'    => 'اسم',
                'phone_number' => '0512345678',
                'password'     => '12',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonValidationErrors(['full_name', 'phone_number', 'password']);
    }

    public function test_update_rejects_email_belonging_to_another_user(): void
    {
        $supervisor = $this->makeSupervisor();
        $other      = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/' . $supervisor->id, [
                'email' => $other->user->email,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_returns_404_for_missing_supervisor(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admins/99999999', ['full_name' => 'اسم ثلاثي جديد']);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'عذراً، المشرف غير موجود.');
    }

    public function test_guest_cannot_update_supervisor(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->postJson('/api/admin/admins/' . $supervisor->id, ['full_name' => 'اسم ثلاثي جديد'])
            ->assertStatus(401);
    }

    // =====================================================================
    // 5️⃣ حذف مشرف — DELETE /api/admin/admins/{id}
    // =====================================================================

    public function test_admin_can_delete_supervisor(): void
    {
        $supervisor = $this->makeSupervisor(['full_name' => 'مشرف سيتم حذفه']);
        $userId     = $supervisor->user_id;

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $supervisor->id);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم حذف المشرف (مشرف سيتم حذفه) بنجاح.');

        // سجل المشرف يُحذف فعلياً (جدول admins بلا عمود deleted_at)،
        // بينما حساب المستخدم يُحذف حذفاً ناعماً حفاظاً على سجل التدقيق وعلى
        // المراجع مثل created_by لمشرفين آخرين. في الحالتين لا يظهر الحساب في
        // أي واجهة عرض ولا يمكنه تسجيل الدخول.
        $this->assertDatabaseMissing('admins', ['id' => $supervisor->id]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_deleted_supervisor_disappears_from_list_and_show(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $supervisor->id)
            ->assertStatus(200);

        $list = $this->actingAs($this->adminUser)->getJson('/api/admin/admins?per_page=100');
        $this->assertNotContains($supervisor->id, array_column($list->json('data.data'), 'id'));

        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admins/' . $supervisor->id)
            ->assertStatus(404);
    }

    public function test_delete_revokes_supervisor_access_tokens(): void
    {
        $supervisor = $this->makeSupervisor();
        $supervisor->user->createToken('test-device');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $supervisor->user_id,
            'tokenable_type' => User::class,
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $supervisor->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $supervisor->user_id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_delete_reassigns_admins_created_by_the_deleted_supervisor(): void
    {
        $creator = $this->makeSupervisor();

        // مشرف آخر أنشأه المشرف الذي سنحذفه
        $child = $this->makeSupervisor();
        $child->update(['created_by' => $creator->user_id]);

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $creator->id)
            ->assertStatus(200);

        // تنتقل الملكية إلى منفّذ الحذف بدل أن تنهار العملية بسبب قيد RESTRICT
        $this->assertDatabaseHas('admins', [
            'id'         => $child->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_delete_removes_avatar_file_from_storage(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('to-delete.jpg')->store('uploads/admins/avatars', 'public');

        $supervisor = $this->makeSupervisor(['avatar_url' => 'storage/' . $path]);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $supervisor->id)
            ->assertStatus(200);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $this->adminProfile->id);

        $response->assertStatus(403);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'لا يمكنك حذف حسابك الشخصي من هنا.');

        $this->assertDatabaseHas('admins', ['id' => $this->adminProfile->id]);
    }

    public function test_root_system_admin_cannot_be_deleted(): void
    {
        // مدير نظام آخر (role_id = 1) محمي من الحذف
        $otherRoot = $this->makeSupervisor(['role_id' => 1]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admins/' . $otherRoot->id);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'لا يمكن حذف حساب مدير النظام الأساسي.');
        $this->assertDatabaseHas('admins', ['id' => $otherRoot->id]);
    }

    public function test_delete_returns_404_for_missing_supervisor(): void
    {
        $response = $this->actingAs($this->adminUser)->deleteJson('/api/admin/admins/99999999');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'عذراً، المشرف غير موجود.');
    }

    public function test_guest_cannot_delete_supervisor(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->deleteJson('/api/admin/admins/' . $supervisor->id)->assertStatus(401);
        $this->assertDatabaseHas('admins', ['id' => $supervisor->id]);
    }

    // =====================================================================
    // 🖼️ مسار صور المشرفين — GET /api/admin/avatars/{filename}
    // =====================================================================

    public function test_avatar_url_points_to_laravel_route_not_static_storage(): void
    {
        Storage::fake('public');
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($this->adminUser)->post(
            '/api/admin/admins/' . $supervisor->id,
            ['avatar' => UploadedFile::fake()->image('pic.jpg')],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotNull($avatarUrl);

        // يجب أن يمر عبر لارافيل ليحصل على ترويسات CORS، لا عبر /storage الثابت
        $this->assertStringContainsString('/api/admin/avatars/', $avatarUrl);
        $this->assertStringNotContainsString('/storage/', $avatarUrl);
    }

    public function test_avatar_is_served_publicly_with_cors_header(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('served.jpg');
        $path = $file->store('uploads/admins/avatars', 'public');

        // بدون توكن إطلاقاً — المتصفح لا يرسل Authorization مع الصور
        $response = $this->get('/api/admin/avatars/' . basename($path));

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function test_avatar_route_returns_404_for_missing_file(): void
    {
        Storage::fake('public');

        $this->get('/api/admin/avatars/doesnotexist.png')->assertStatus(404);
    }

    public function test_avatar_route_blocks_path_traversal_and_bad_extensions(): void
    {
        Storage::fake('public');

        $this->get('/api/admin/avatars/' . urlencode('../../.env'))->assertStatus(404);
        $this->get('/api/admin/avatars/shell.php')->assertStatus(404);
    }

    // =====================================================================
    // 🔄 اختبار تكاملي لدورة الحياة الكاملة
    // =====================================================================

    public function test_full_supervisor_lifecycle_end_to_end(): void
    {
        $email = 'lifecycle.' . uniqid() . '@darby.test';

        // إنشاء
        $created = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'دورة حياة كاملة',
            'email'        => $email,
            'phone_number' => '097' . rand(1000000, 9999999),
            'password'     => 'lifecycle123',
        ])->assertStatus(201);

        $id = $created->json('data.id');

        // ظهوره في القائمة
        $list = $this->actingAs($this->adminUser)->getJson('/api/admin/admins?per_page=100');
        $this->assertContains($id, array_column($list->json('data.data'), 'id'));

        // عرضه منفرداً
        $this->actingAs($this->adminUser)->getJson('/api/admin/admins/' . $id)
            ->assertStatus(200)
            ->assertJsonPath('data.email', $email);

        // تعديله
        $this->actingAs($this->adminUser)->postJson('/api/admin/admins/' . $id, [
            'full_name' => 'دورة حياة معدلة',
            'is_active' => false,
        ])->assertStatus(200)->assertJsonPath('data.full_name', 'دورة حياة معدلة');

        // حذفه
        $this->actingAs($this->adminUser)->deleteJson('/api/admin/admins/' . $id)
            ->assertStatus(200);

        // اختفاؤه نهائياً
        $this->actingAs($this->adminUser)->getJson('/api/admin/admins/' . $id)
            ->assertStatus(404);
    }
}
