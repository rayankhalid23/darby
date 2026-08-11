# 👤 دليل ربط الملف الشخصي (Admin / Supervisor Profile API)

> وثيقة مخصصة لمطوّر الواجهة الأمامية.
> كل نماذج الـ JSON هنا **ملتقطة من استجابات حقيقية** بعد اختبار كل نقطة، وليست أمثلة مكتوبة يدوياً.
> مغطاة بـ **28 اختباراً آلياً ناجحاً** في `tests/Feature/AdminProfileTest.php`.

---

## 1. الفكرة العامة

هذه المسارات تخص **حساب صاحب التوكن نفسه** — لا تُمرَّر أي `id` إطلاقاً.

**نفس المسارات تخدم الدورين معاً:**
- `role_id = 1` → مدير النظام
- `role_id = 2` → مشرف

لا يوجد أي فرق في السلوك أو الصلاحيات بينهما في البروفايل، ولا حاجة لكتابة شاشتين.

| البند | القيمة |
|---|---|
| **Base URL** | `{APP_URL}/api/admin` |
| **المصادقة** | `Authorization: Bearer {token}` + `Accept: application/json` |
| **التعديل** | `POST` وليس `PUT` (لدعم رفع الصورة multipart) |

---

## 2. جدول نقاط النهاية

| # | العملية | Method | المسار |
|---|---|---|---|
| 1 | عرض ملفي الشخصي | `GET` | `/api/admin/profile` |
| 2 | تعديل ملفي الشخصي | `POST` | `/api/admin/profile` |
| 3 | حالة طلب تغيير بريدي | `GET` | `/api/admin/profile/email-change/status` |
| 4 | إلغاء طلب تغيير بريدي | `POST` | `/api/admin/profile/email-change/cancel` |
| 5 | إعادة إرسال رابط التأكيد | `POST` | `/api/admin/profile/email-change/resend` |

---

## 3. عرض الملف الشخصي

```
GET /api/admin/profile
```

### ✅ نجاح (200)

```json
{
  "status": true,
  "message": "تم جلب بيانات الملف الشخصي بنجاح.",
  "data": {
    "id": 1,
    "user_id": 1,
    "full_name": "أحمد المدير",
    "email": "admin@derbi.ly",
    "phone_number": "0900000000",
    "avatar_url": null,
    "is_active": true,
    "role_id": 1,
    "role_name": "مدير النظام",
    "created_by": 1,
    "creator_name": "أحمد المدير",
    "created_at": "2026-07-19 01:30:56",
    "last_login_at": null,
    "email_change_pending": false,
    "pending_new_email": null
  }
}
```

> 🔵 **الكائن مطابق تماماً** لكائن المشرف في وحدة إدارة المشرفين — نفس الحقول ونفس الأنواع.
> تقدر تعيد استخدام نفس الموديل (`AdminModel`) بلا أي تعديل.

| الحقل | ملاحظات للعرض |
|---|---|
| `avatar_url` | رابط مطلق جاهز لـ `Image.network` مباشرة، أو `null` |
| `role_name` | `"مدير النظام"` أو `"مشرف"` — جاهز للعرض بلا ترجمة |
| `email_change_pending` | `true` → اعرض شارة صفراء "بانتظار تأكيد البريد" |
| `pending_new_email` | البريد الجديد المنتظر تأكيده |
| `is_active` | للعرض فقط — **لا يمكن للمستخدم تعديله على نفسه** |

### ❌ 404 — الحساب ليس مشرفاً

```json
{ "status": false, "message": "حسابك غير مسجل ضمن المشرفين." }
```

يحدث لو كان المستخدم بدور مشرف لكن بلا سجل في جدول المشرفين. عالجها برسالة واضحة وتسجيل خروج.

### ❌ 401 — بلا توكن

```json
{ "status": false, "error_code": "UNAUTHENTICATED", "message": "غير مصرح بالوصول، يرجى تسجيل الدخول أولاً." }
```

---

## 4. تعديل الملف الشخصي

```
POST /api/admin/profile
```

### بيانات الإدخال — **كل الحقول اختيارية (تحديث جزئي)**

أرسل فقط ما تغيّر.

| الحقل | النوع | الشروط |
|---|---|---|
| `full_name` | string | اسم ثلاثي على الأقل + غير مكرر |
| `email` | string | صيغة صحيحة + غير مكرر — **يمر بخطوة تأكيد** |
| `phone_number` | string | 10 أرقام تبدأ بـ `09` + غير مكرر |
| `current_password` | string | **إجباري فقط عند إرسال `password`** |
| `password` | string | 6 خانات فأكثر |
| `password_confirmation` | string | **إجباري مع `password`** ويجب أن يطابقه |
| `avatar` | file | `jpg/jpeg/png` بحد أقصى 2 ميجابايت |

> 🔴 **`is_active` غير مقبول هنا إطلاقاً.** لو أرسلته سيُتجاهل بصمت ولن يتغير شيء —
> لا يستطيع المستخدم إيقاف أو تفعيل حسابه بنفسه. لإيقاف مشرف استخدم وحدة إدارة المشرفين.

### ✅ نجاح (200)

```json
{
  "status": true,
  "message": "تم تحديث ملفك الشخصي بنجاح.",
  "data": { "...": "كائن البروفايل الكامل بعد التحديث" },
  "email_verification": null
}
```

### 🔐 تغيير كلمة المرور

```json
{
  "current_password": "password123",
  "password": "newPassword456",
  "password_confirmation": "newPassword456"
}
```

**كلمة مرور حالية خاطئة → 422:**
```json
{
  "status": false,
  "message": "عذراً، البيانات المرسلة لتعديل الملف الشخصي تحتوي على أخطاء.",
  "errors": {
    "current_password": ["كلمة المرور الحالية غير صحيحة."]
  }
}
```

**رسائل أخرى محتملة:**

| الحالة | الرسالة |
|---|---|
| إرسال `password` بلا `current_password` | يجب إدخال كلمة المرور الحالية لتغيير كلمة المرور. |
| التأكيد غير مطابق | تأكيد كلمة المرور غير مطابق. |
| أقل من 6 خانات | يجب ألا تقل كلمة المرور عن 6 خانات. |

> ℹ️ تغيير كلمة المرور **لا يُلغي توكن الجلسة الحالية** — المستخدم يبقى مسجّل الدخول.

### 📧 تغيير البريد الإلكتروني

عند إرسال `email` مختلف عن الحالي، **البريد لا يتغير فوراً**. يبقى القديم شغّالاً،
ويُرسل رابط تأكيد للبريد الجديد صالح **30 دقيقة**. البريد يتغير فعلياً فقط بعد فتح الرابط.

**الرد (200):**
```json
{
  "status": true,
  "message": "تم تحديث بياناتك بنجاح. أرسلنا رابط تأكيد لبريدك الجديد، يرجى فتحه لتفعيل التغيير.",
  "data": {
    "email": "admin@derbi.ly",
    "email_change_pending": true,
    "pending_new_email": "brand.new@derbi.ly",
    "...": "بقية الحقول"
  },
  "email_verification": {
    "status": "pending",
    "new_email": "brand.new@derbi.ly",
    "expires_at": "2026-08-10 23:53:25"
  }
}
```

> ⚠️ **لاحظ:** `data.email` ما زال **البريد القديم** — هذا صحيح ومقصود، ليس خطأ.
> البريد الجديد المنتظر في `data.pending_new_email`.

**المنطق في الواجهة:**
```dart
final verification = response.data['email_verification'];

if (verification != null) {
  showEmailVerificationDialog(newEmail: verification['new_email']);
} else {
  showSuccess(response.data['message']);
}
```

> إرسال نفس البريد الحالي بلا تغيير **لا يسبب خطأ** ولا يرسل رسالة — يرجع `email_verification: null`.

### ❌ أخطاء التحقق (422)

```json
{
  "status": false,
  "message": "عذراً، البيانات المرسلة لتعديل الملف الشخصي تحتوي على أخطاء.",
  "errors": {
    "full_name": ["الرجاء إدخال الاسم الثلاثي بالكامل."],
    "phone_number": [
      "رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط.",
      "رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09."
    ]
  }
}
```

**كل رسائل التحقق:**

| الحقل | الحالة | الرسالة |
|---|---|---|
| `full_name` | أقل من 3 كلمات | الرجاء إدخال الاسم الثلاثي بالكامل. |
| `full_name` | مكرر | هذا الاسم مسجل في النظام مسبقاً. |
| `email` | صيغة خاطئة | صيغة البريد الإلكتروني غير صحيحة. |
| `email` | مستخدم لحساب آخر | البريد الإلكتروني مستخدم بالفعل لحساب آخر. |
| `phone_number` | ≠ 10 أرقام | رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط. |
| `phone_number` | لا يبدأ بـ 09 | رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09. |
| `phone_number` | مستخدم لحساب آخر | رقم الهاتف هذا مستخدم لحساب آخر. |
| `avatar` | ليس صورة | الملف المرفق يجب أن يكون صورة. |
| `avatar` | أكبر من 2MB | حجم الصورة يجب ألا يتجاوز 2 ميجابايت. |

---

## 5. حالة طلب تغيير البريد

```
GET /api/admin/profile/email-change/status
```

```json
{
  "status": true,
  "data": {
    "status": "pending",
    "new_email": "brand.new@derbi.ly"
  }
}
```

| `data.status` | المعنى | الإجراء |
|---|---|---|
| `pending` | لم يُفتح الرابط بعد | أبقِ النافذة + "لم يتم التأكيد بعد، افتح الرابط في بريدك" |
| `verified` | ✅ تم التأكيد والبريد تغيّر فعلاً | أغلق النافذة + أعد تحميل البروفايل + رسالة نجاح |
| `rejected` | المستخدم ضغط رابط الرفض | أغلق النافذة + "تم رفض طلب تغيير البريد" |
| `expired` | انتهت الـ 30 دقيقة أو لا يوجد طلب | أغلق النافذة + "انتهت صلاحية الرابط، أعد المحاولة" |

> في الحالات `verified` و `rejected` و `expired` تكون `new_email` = `null`.

---

## 6. إلغاء طلب تغيير البريد

```
POST /api/admin/profile/email-change/cancel
```
بلا Body.

**نجاح (200):**
```json
{ "status": true, "message": "تم إلغاء طلب تغيير البريد الإلكتروني." }
```

**لا يوجد طلب معلّق (400):**
```json
{ "status": false, "message": "لا يوجد طلب معلق لتغيير البريد الإلكتروني." }
```

بعد الإلغاء يتوقف الرابط القديم، ويصير `email_change_pending = false`.

---

## 7. إعادة إرسال رابط التأكيد

```
POST /api/admin/profile/email-change/resend
```
بلا Body.

```json
{
  "status": true,
  "message": "تمت إعادة إرسال رابط التأكيد بنجاح.",
  "email_verification": {
    "status": "pending",
    "new_email": "brand.new@derbi.ly",
    "expires_at": "2026-08-10 23:53:25"
  }
}
```

> يُبطل الرابط القديم ويصدر رابطاً جديداً **بمهلة 30 دقيقة جديدة**.
> لو لا يوجد طلب معلّق ترجع **400**.

---

## 8. ملخص أكواد الحالة

| الكود | المعنى | الإجراء |
|---|---|---|
| `200` | نجحت | اعرض `message` وحدّث البيانات |
| `400` | لا يوجد طلب معلّق | اعرض `message` كتحذير |
| `401` | غير مصادق | سجّل الخروج ووجّه للدخول |
| `404` | الحساب ليس مشرفاً | رسالة واضحة + تسجيل خروج |
| `422` | بيانات غير صالحة | وزّع `errors` على حقول النموذج |
| `500` | خطأ سيرفر | رسالة عامة |

---

## 9. كود جاهز (Axios / Dio)

```dart
// 1) عرض البروفايل
Future<AdminModel> getMyProfile() async {
  final res = await api.get('/api/admin/profile');
  return AdminModel.fromJson(res.data['data']);
}

// 2) تعديل البروفايل (يدعم الصورة)
Future<Map<String, dynamic>> updateMyProfile({
  String? fullName,
  String? email,
  String? phoneNumber,
  String? currentPassword,
  String? password,
  String? passwordConfirmation,
  File? avatar,
}) async {
  final form = FormData.fromMap({
    if (fullName != null)             'full_name': fullName,
    if (email != null)                'email': email,
    if (phoneNumber != null)          'phone_number': phoneNumber,
    if (currentPassword != null)      'current_password': currentPassword,
    if (password != null)             'password': password,
    if (passwordConfirmation != null) 'password_confirmation': passwordConfirmation,
    if (avatar != null)               'avatar': await MultipartFile.fromFile(avatar.path),
  });

  final res = await api.post('/api/admin/profile', data: form);
  return {
    'profile':      AdminModel.fromJson(res.data['data']),
    'message':      res.data['message'],
    'verification': res.data['email_verification'], // null أو كائن
  };
}

// 3) حالة تغيير البريد
Future<String> myEmailChangeStatus() async {
  final res = await api.get('/api/admin/profile/email-change/status');
  return res.data['data']['status']; // pending|verified|rejected|expired
}

// 4) إلغاء
Future<String> cancelMyEmailChange() async {
  final res = await api.post('/api/admin/profile/email-change/cancel');
  return res.data['message'];
}

// 5) إعادة إرسال
Future<String> resendMyEmailChange() async {
  final res = await api.post('/api/admin/profile/email-change/resend');
  return res.data['message'];
}
```

---

## 10. حسابات جاهزة للاختبار

الدخول بـ**رقم الهاتف** (وليس البريد) و`platform` إجباري:

```json
{ "phone_number": "0900111222", "password": "password123", "platform": "web" }
```

| الدور | الهاتف | البريد | كلمة المرور |
|---|---|---|---|
| مدير النظام | `0900111222` | superadmin@derbi.ly | `password123` |
| مشرف | `0900333444` | supervisor@derbi.ly | `password123` |
| مشرف | `0913114455` | khaled.supervisor@derbi.ly | `password123` |
| مشرف | `0924225566` | mariam.supervisor@derbi.ly | `password123` |

**جرّب الدورين** — البروفايل يعمل بنفس الشكل تماماً، والفرق الوحيد `role_id` و `role_name`.

لإعادة إنشاء الحسابات:
```bash
php artisan db:seed --class=DemoLoginAccountsSeeder
```

---

## 11. ✅ ملخص ما يجب بناؤه

1. **شاشة عرض البروفايل** — نفس موديل `AdminModel` المستخدم في إدارة المشرفين.
2. **نموذج تعديل** بحقول: الاسم، البريد، الهاتف، الصورة.
3. **قسم منفصل لتغيير كلمة المرور** بثلاثة حقول (الحالية + الجديدة + التأكيد).
4. **إعادة استخدام نافذة تأكيد البريد** نفسها الموجودة في إدارة المشرفين — نفس المنطق تماماً، فقط المسارات بلا `{id}`.
5. **شارة "بانتظار تأكيد البريد"** عند `email_change_pending == true`.
6. **لا تعرض حقل `is_active`** كعنصر قابل للتعديل في البروفايل — للعرض فقط.
