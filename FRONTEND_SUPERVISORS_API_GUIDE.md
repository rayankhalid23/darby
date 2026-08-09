# 👨‍💼 دليل ربط وحدة إدارة المشرفين (Supervisors / Admins Management API)

> وثيقة مخصصة لمطوّر الواجهة الأمامية.
> كل النماذج في هذا الملف **مأخوذة من استجابات حقيقية** تم التقاطها من السيرفر بعد اختبار كل نقطة نهاية،
> وليست أمثلة مكتوبة يدوياً.

---

## 1. الإعدادات العامة للطلب

| البند | القيمة |
|---|---|
| **Base URL** | `{APP_URL}/api/admin` |
| **المصادقة** | Laravel Sanctum — Bearer Token |
| **الترويسات المطلوبة** | `Authorization: Bearer {token}` و `Accept: application/json` |
| **ترميز النصوص** | UTF-8 (كل الرسائل بالعربية) |

### الحصول على التوكن
```
POST /api/auth/login
```
> ⚠️ تسجيل الدخول يتم بـ **رقم الهاتف** وليس بالبريد الإلكتروني، وحقل `platform` **إجباري**.

```json
{
  "phone_number": "0900000000",
  "password": "password123",
  "platform": "web",
  "device_name": "admin-dashboard"
}
```
| الحقل | مطلوب؟ | الشروط |
|---|---|---|
| `phone_number` | ✅ نعم | 10 أرقام تبدأ بـ `09` |
| `password` | ✅ نعم | — |
| `platform` | ✅ نعم | `web` أو `ios` أو `android` فقط |
| `device_name` | ❌ لا | أي نص |
استخدم التوكن الراجع في كل الطلبات التالية:
```
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

### ⚠️ ملاحظتان مهمتان قبل البدء
1. **التعديل يستخدم `POST` وليس `PUT`/`PATCH`** — وذلك لأن الواجهة قد ترسل صورة (multipart)، و PHP لا يقرأ الملفات مع `PUT`.
2. **كل الاستجابات تحتوي على المفتاح `status`** (`true` عند النجاح و `false` عند الفشل)، فاعتمد عليه في الواجهة قبل قراءة `data`.

---

## 2. جدول ملخّص نقاط النهاية

| # | العملية | Method | المسار | نجاح |
|---|---|---|---|---|
| 1 | عرض كل المشرفين | `GET` | `/api/admin/admins` | 200 |
| 2 | عرض بيانات مشرف معيّن | `GET` | `/api/admin/admins/{id}` | 200 |
| 3 | إضافة مشرف جديد | `POST` | `/api/admin/admins` | 201 |
| 4 | تعديل بيانات مشرف | `POST` | `/api/admin/admins/{id}` | 200 |
| 5 | حذف مشرف | `DELETE` | `/api/admin/admins/{id}` | 200 |
| 6 | تأكيد تغيير البريد | `GET` | `/api/admin/admin/email/approve/{token}` | 200 |
| 7 | رفض تغيير البريد | `GET` | `/api/admin/admin/email/reject/{token}` | 200 |

---

## 3. كائن المشرف الموحّد (Admin Object)

نفس الشكل يرجع في **كل** العمليات (قائمة / عرض / إضافة / تعديل):

```json
{
  "id": 9,
  "user_id": 22,
  "full_name": "سارة توفيق العجيلي",
  "email": "sara.supervisor@derbi.ly",
  "phone_number": "0928669900",
  "avatar_url": null,
  "is_active": true,
  "role_id": 2,
  "role_name": "مشرف",
  "created_by": 1,
  "creator_name": "أحمد المدير",
  "created_at": "2026-08-09 22:17:51",
  "last_login_at": null
}
```

| الحقل | النوع | الوصف |
|---|---|---|
| `id` | integer | معرّف سجل المشرف — **هذا هو الذي يُستخدم في كل المسارات** `{id}` |
| `user_id` | integer | معرّف حساب المستخدم المرتبط (للاستخدام الداخلي، لا يُستخدم في المسارات) |
| `full_name` | string | الاسم الثلاثي |
| `email` | string | البريد الإلكتروني |
| `phone_number` | string | رقم الهاتف (10 أرقام يبدأ بـ `09`) |
| `avatar_url` | string\|null | رابط الصورة كامل وجاهز للعرض مباشرة في `<img>`، أو `null` |
| `is_active` | boolean | حالة التفعيل — اعرضها كـ Toggle أو Badge |
| `role_id` | integer | `1` = مدير النظام، `2` = مشرف |
| `role_name` | string | `"مدير النظام"` أو `"مشرف"` — جاهز للعرض |
| `created_by` | integer | معرّف مستخدم من أنشأ الحساب |
| `creator_name` | string\|null | اسم من أنشأ الحساب — جاهز للعرض |
| `created_at` | string\|null | `Y-m-d H:i:s` |
| `last_login_at` | string\|null | آخر دخول، أو `null` إن لم يسجّل الدخول بعد |

---

## 4. عرض كل المشرفين

```
GET /api/admin/admins
```

### باراميترات الاستعلام (اختيارية)

| الباراميتر | النوع | الافتراضي | الوصف |
|---|---|---|---|
| `per_page` | integer | `10` | عدد العناصر في الصفحة |
| `page` | integer | `1` | رقم الصفحة |
| `search` | string | — | بحث جزئي في الاسم أو البريد أو رقم الهاتف |

**أمثلة:**
```
GET /api/admin/admins?per_page=3
GET /api/admin/admins?per_page=10&page=2
GET /api/admin/admins?search=سارة
GET /api/admin/admins?search=0928669900
```

### ✅ استجابة النجاح (200)

```json
{
  "status": true,
  "message": "تم جلب قائمة المشرفين بنجاح.",
  "data": {
    "data": [
      {
        "id": 9,
        "user_id": 22,
        "full_name": "سارة توفيق العجيلي",
        "email": "sara.supervisor@derbi.ly",
        "phone_number": "0928669900",
        "avatar_url": null,
        "is_active": true,
        "role_id": 2,
        "role_name": "مشرف",
        "created_by": 1,
        "creator_name": "أحمد المدير",
        "created_at": "2026-08-09 22:17:51",
        "last_login_at": null
      },
      {
        "id": 8,
        "user_id": 21,
        "full_name": "عمر ناصر الورفلي",
        "email": "omar.supervisor@derbi.ly",
        "phone_number": "0917558899",
        "avatar_url": null,
        "is_active": false,
        "role_id": 2,
        "role_name": "مشرف",
        "created_by": 1,
        "creator_name": "أحمد المدير",
        "created_at": "2026-08-09 22:17:50",
        "last_login_at": null
      }
    ],
    "links": {
      "first": "http://localhost/api/admin/admins?page=1",
      "last": "http://localhost/api/admin/admins?page=3",
      "prev": null,
      "next": "http://localhost/api/admin/admins?page=2"
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 3,
      "path": "http://localhost/api/admin/admins",
      "per_page": 3,
      "to": 3,
      "total": 9
    }
  }
}
```

> 🔴 **انتبه للتداخل:** القائمة موجودة في `response.data.data` وليس `response.data`.
> وبيانات الترقيم في `response.data.meta` (`current_page`, `last_page`, `per_page`, `total`).

**مثال قراءة في جافاسكربت:**
```js
const res = await api.get('/api/admin/admins', { params: { per_page: 10, page } });
const supervisors = res.data.data.data;     // المصفوفة
const pagination  = res.data.data.meta;     // الترقيم
```

**ملاحظات:**
- الترتيب **تنازلي** — الأحدث إضافةً يظهر أولاً.
- المشرفون المحذوفون لا يظهرون في القائمة إطلاقاً.

---

## 5. عرض بيانات مشرف معيّن

```
GET /api/admin/admins/{id}
```

### ✅ استجابة النجاح (200)

```json
{
  "status": true,
  "message": "تم جلب بيانات المشرف.",
  "data": {
    "id": 9,
    "user_id": 22,
    "full_name": "سارة توفيق العجيلي",
    "email": "sara.supervisor@derbi.ly",
    "phone_number": "0928669900",
    "avatar_url": null,
    "is_active": true,
    "role_id": 2,
    "role_name": "مشرف",
    "created_by": 1,
    "creator_name": "أحمد المدير",
    "created_at": "2026-08-09 22:17:51",
    "last_login_at": null
  }
}
```

### ❌ غير موجود (404)
```json
{
  "status": false,
  "message": "عذراً، المشرف غير موجود."
}
```

---

## 6. إضافة مشرف جديد

```
POST /api/admin/admins
```

### بيانات الإدخال

| الحقل | النوع | مطلوب؟ | الشروط |
|---|---|---|---|
| `full_name` | string | ✅ نعم | **اسم ثلاثي على الأقل** (3 كلمات مفصولة بمسافات) + غير مكرر |
| `email` | string | ✅ نعم | صيغة بريد صحيحة + غير مكرر |
| `phone_number` | string | ✅ نعم | **10 أرقام بالضبط** + **يبدأ بـ `09`** + غير مكرر |
| `password` | string | ❌ لا | 6 خانات فأكثر. **إن تُرك فارغاً يولّد النظام كلمة مرور عشوائية ويرسلها للمشرف على بريده تلقائياً** |
| `avatar` | file | ❌ لا | صورة `jpeg/png/jpg`، بحد أقصى **2 ميجابايت** |

> ℹ️ الحقول `role_id` و `is_active` و `created_by` **يضبطها السيرفر تلقائياً** — لا ترسلها.
> كل مشرف جديد يُنشأ بـ `role_id = 2` و `is_active = true`، و `created_by` = صاحب التوكن الحالي.
>
> ℹ️ لا يوجد حقل `password_confirmation` — لا ترسله.

**JSON (بدون صورة):**
```json
{
  "full_name": "مشرف نموذج التوثيق",
  "email": "docs.sample@derbi.ly",
  "phone_number": "0981721312",
  "password": "secret123"
}
```

**FormData (مع صورة):**
```
full_name:    مشرف نموذج التوثيق
email:        docs.sample@derbi.ly
phone_number: 0981721312
password:     secret123
avatar:       (ملف)
```

### ✅ استجابة النجاح (201)

```json
{
  "status": true,
  "message": "تم إضافة المشرف بنجاح.",
  "data": {
    "id": 10,
    "user_id": 23,
    "full_name": "مشرف نموذج التوثيق",
    "email": "docs.sample@derbi.ly",
    "phone_number": "0981721312",
    "avatar_url": null,
    "is_active": true,
    "role_id": 2,
    "role_name": "مشرف",
    "created_by": 1,
    "creator_name": "أحمد المدير",
    "created_at": "2026-08-09 22:53:02",
    "last_login_at": null
  }
}
```

### ❌ أخطاء التحقق (422)

```json
{
  "status": false,
  "message": "عذراً، مدخلات إنشاء الحساب تحتوي على أخطاء.",
  "errors": {
    "full_name": [
      "الرجاء إدخل الاسم الثلاثي للمشرف بالكامل لتوثيق الحساب."
    ],
    "email": [
      "صيغة البريد الإلكتروني غير صحيحة."
    ],
    "phone_number": [
      "رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط.",
      "رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09."
    ],
    "password": [
      "كلمة المرور يجب ألا تقل عن 6 خانات."
    ]
  }
}
```

> `errors` كائن مفاتيحه أسماء الحقول وقيمه **مصفوفة رسائل**. اعرض أول رسالة تحت كل حقل.

**كل رسائل التحقق الممكنة:**

| الحقل | الحالة | الرسالة |
|---|---|---|
| `full_name` | فارغ | حقل الاسم الكامل مطلوب، لا يمكنك تركه فارغاً. |
| `full_name` | أقل من 3 كلمات | الرجاء إدخل الاسم الثلاثي للمشرف بالكامل لتوثيق الحساب. |
| `full_name` | مكرر | هذا الاسم مسجل في النظام مسبقاً، الرجاء اختيار اسم مختلف. |
| `email` | فارغ | البريد الإلكتروني حقل إجباري لتسجيل حساب المشرف. |
| `email` | صيغة خاطئة | صيغة البريد الإلكتروني غير صحيحة. |
| `email` | مكرر | البريد الإلكتروني هذا مستخدم لحساب آخر في النظام. |
| `phone_number` | فارغ | رقم الهاتف مطلوب لاستكمال عملية التسجيل. |
| `phone_number` | ليس أرقاماً | رقم الهاتف يجب أن يحتوي على أرقام فقط. |
| `phone_number` | ≠ 10 أرقام | رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط. |
| `phone_number` | لا يبدأ بـ 09 | رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09. |
| `phone_number` | مكرر | رقم الهاتف هذا مستخدم لحساب آخر بالفعل. |
| `password` | أقل من 6 | كلمة المرور يجب ألا تقل عن 6 خانات. |
| `avatar` | ليس صورة | الملف المرفق يجب أن يكون صورة. |
| `avatar` | صيغة خاطئة | يجب أن تكون الصورة بصيغة jpeg, png, أو jpg. |
| `avatar` | أكبر من 2MB | حجم الصورة يجب ألا يتجاوز 2 ميجابايت. |

---

## 7. تعديل بيانات مشرف

```
POST /api/admin/admins/{id}
```

### بيانات الإدخال — **كل الحقول اختيارية (تحديث جزئي)**

أرسل فقط الحقول التي تغيّرت؛ الباقي يبقى كما هو.

| الحقل | النوع | الشروط |
|---|---|---|
| `full_name` | string | اسم ثلاثي + غير مكرر (يتجاهل المشرف نفسه) |
| `email` | string | صيغة صحيحة + غير مكرر (يتجاهل المشرف نفسه) — **يمرّ بخطوة تأكيد، انظر أدناه** |
| `phone_number` | string | 10 أرقام يبدأ بـ `09` + غير مكرر (يتجاهل المشرف نفسه) |
| `password` | string | 6 خانات فأكثر — إن لم تُرسل تبقى كلمة المرور القديمة |
| `is_active` | boolean | `true` = مفعّل، `false` = موقوف |
| `avatar` | file | صورة `jpeg/png/jpg` بحد أقصى 2MB — تستبدل القديمة وتحذفها من التخزين |

**مثال:**
```json
{
  "full_name": "مشرف نموذج معدل",
  "is_active": false
}
```

### ✅ استجابة النجاح (200)

```json
{
  "status": true,
  "message": "تم تحديث بيانات المشرف بنجاح.",
  "data": {
    "id": 10,
    "user_id": 23,
    "full_name": "مشرف نموذج معدل",
    "email": "docs.sample@derbi.ly",
    "phone_number": "0981721312",
    "avatar_url": null,
    "is_active": false,
    "role_id": 2,
    "role_name": "مشرف",
    "created_by": 1,
    "creator_name": "أحمد المدير",
    "created_at": "2026-08-09 22:53:02",
    "last_login_at": null
  }
}
```

### 📧 حالة خاصة: تغيير البريد الإلكتروني

عند إرسال `email` مختلف عن الحالي، **لا يتغير البريد فوراً**. يرسل السيرفر رابط تأكيد
إلى البريد الجديد، وتكون الاستجابة `200` برسالة مختلفة:

```json
{
  "status": true,
  "message": "تم تحديث البيانات المرفقة بنجاح. أرسلنا رابط تأكيد لبريدك الجديد، يرجى مراجعته لتفعيله بكبسة زر.",
  "data": { "...": "الحقول الأخرى محدّثة، لكن email لا يزال القديم" }
}
```

**ما يجب على الواجهة فعله:**
- افحص إن كانت الرسالة تحتوي على "أرسلنا رابط تأكيد" واعرض تنبيهاً أصفر للمستخدم.
- **لا تفترض أن البريد تغيّر** — أعد قراءة `data.email` واعرضه كما هو.
- الرابط صالح **30 دقيقة** فقط.
- الروابط تُفتح من بريد المشرف مباشرة (لا تحتاج الواجهة لاستدعائها):
  - قبول: `GET /api/admin/admin/email/approve/{token}` → `{"status": true, "message": "تم تفعيل وتحديث بريدك الإلكتروني بنجاح! 🎉"}`
  - رفض: `GET /api/admin/admin/email/reject/{token}` → `{"status": true, "message": "تم إلغاء طلب تغيير البريد الإلكتروني بنجاح."}`

> إرسال نفس البريد الحالي دون تغيير **لا يسبب أي خطأ** ولا يرسل رسالة تأكيد.

### ❌ أخطاء التعديل

**422 — بيانات غير صالحة:**
```json
{
  "status": false,
  "message": "عذراً، البيانات المرسلة لتعديل المشرف تحتوي على أخطاء.",
  "errors": {
    "full_name": ["الرجاء إدخال الاسم الثلاثي بالكامل."],
    "phone_number": ["رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09."],
    "password": ["يجب ألا تقل كلمة المرور عن 6 خانات."]
  }
}
```

**404 — المشرف غير موجود:**
```json
{
  "status": false,
  "message": "عذراً، المشرف غير موجود."
}
```

---

## 8. حذف مشرف

```
DELETE /api/admin/admins/{id}
```

لا يحتاج أي Body.

### ✅ استجابة النجاح (200)

```json
{
  "status": true,
  "message": "تم حذف المشرف (مشرف نموذج معدل) نهائياً بنجاح."
}
```

> لاحظ: لا يوجد مفتاح `data` في استجابة الحذف — فقط `status` و `message`.
> الرسالة تتضمن اسم المشرف المحذوف، فيمكن عرضها مباشرة في Toast.

### ما الذي يحدث فعلياً عند الحذف
1. تُلغى كل جلسات دخول المشرف فوراً (التوكنات تُحذف).
2. تُحذف صورته الشخصية من التخزين.
3. إذا كان هذا المشرف قد أنشأ مشرفين آخرين، تنتقل ملكيتهم تلقائياً لمن نفّذ الحذف.
4. يُحذف سجل المشرف وحساب المستخدم **نهائياً** (حذف غير قابل للتراجع).

> ⚠️ **الحذف نهائي.** اعرض نافذة تأكيد قبل استدعاء هذه النقطة.

### ❌ حالات المنع

**403 — محاولة حذف حسابك الشخصي:**
```json
{
  "status": false,
  "message": "لا يمكنك حذف حسابك الشخصي من هنا."
}
```

**403 — محاولة حذف مدير النظام الأساسي (`role_id = 1`):**
```json
{
  "status": false,
  "message": "لا يمكن حذف حساب مدير النظام الأساسي."
}
```

**404 — المشرف غير موجود أو محذوف مسبقاً:**
```json
{
  "status": false,
  "message": "عذراً، المشرف غير موجود."
}
```

> 💡 **نصيحة للواجهة:** أخفِ زر الحذف عن الصفوف التي يكون فيها `role_id === 1`
> أو `user_id === currentUser.id` بدلاً من انتظار الخطأ 403.

---

## 9. أخطاء عامة تنطبق على كل النقاط

**401 — التوكن مفقود أو منتهي:**
```json
{
  "status": false,
  "error_code": "UNAUTHENTICATED",
  "message": "غير مصرح بالوصول، يرجى تسجيل الدخول أولاً."
}
```
> عند استقبال 401 وجّه المستخدم لصفحة تسجيل الدخول واحذف التوكن المخزّن.

**500 — خطأ داخلي:**
```json
{
  "status": false,
  "message": "حدث خطأ في النظام."
}
```

### ملخص أكواد الحالة

| الكود | المعنى | الإجراء في الواجهة |
|---|---|---|
| `200` | نجحت العملية | اعرض `message` وحدّث البيانات |
| `201` | تم الإنشاء | اعرض `message` وأضف العنصر للقائمة |
| `401` | غير مصادق | سجّل الخروج ووجّه للدخول |
| `403` | ممنوع | اعرض `message` كتحذير |
| `404` | غير موجود | اعرض `message` وحدّث القائمة |
| `422` | بيانات غير صالحة | وزّع `errors` على حقول النموذج |
| `500` | خطأ سيرفر | اعرض رسالة عامة |

---

## 10. البيانات الوهمية الجاهزة للاختبار

لتعبئة قاعدة البيانات بـ 8 مشرفين وهميين:

```bash
php artisan db:seed --class=SupervisorsDemoSeeder
```

**السيدر آمن وقابل لإعادة التشغيل** (لا يُكرر البيانات).
كلمة المرور لجميع الحسابات: `password123`

| # | الاسم | البريد | الهاتف | الحالة |
|---|---|---|---|---|
| 1 | علي عمر المشرف | ali.supervisor@derbi.ly | 0911002200 | ✅ مفعّل |
| 2 | فاطمة محمد الترهوني | fatima.supervisor@derbi.ly | 0922003300 | ✅ مفعّل |
| 3 | خالد سالم الزنتاني | khaled.supervisor@derbi.ly | 0913114455 | ✅ مفعّل |
| 4 | مريم عبدالله المصراتي | mariam.supervisor@derbi.ly | 0924225566 | ✅ مفعّل |
| 5 | يوسف مصطفى بن سعيد | youssef.supervisor@derbi.ly | 0915336677 | ⛔ موقوف |
| 6 | هدى إبراهيم القذافي | huda.supervisor@derbi.ly | 0926447788 | ✅ مفعّل |
| 7 | عمر ناصر الورفلي | omar.supervisor@derbi.ly | 0917558899 | ⛔ موقوف |
| 8 | سارة توفيق العجيلي | sara.supervisor@derbi.ly | 0928669900 | ✅ مفعّل |

بالإضافة إلى حساب مدير النظام للدخول: `admin@derbi.ly` / `password123`

> السيدر يشمل حسابين موقوفين عمداً حتى تتمكن من اختبار عرض حالة `is_active` في الواجهة.

---

## 11. أمثلة جاهزة (Axios)

```js
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// 1) عرض كل المشرفين
export const getSupervisors = async ({ page = 1, perPage = 10, search = '' } = {}) => {
  const { data } = await api.get('/api/admin/admins', {
    params: { page, per_page: perPage, search: search || undefined },
  });
  return { items: data.data.data, pagination: data.data.meta };
};

// 2) عرض مشرف معيّن
export const getSupervisor = async (id) => {
  const { data } = await api.get(`/api/admin/admins/${id}`);
  return data.data;
};

// 3) إضافة مشرف (مع دعم الصورة)
export const createSupervisor = async (payload) => {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') form.append(key, value);
  });
  const { data } = await api.post('/api/admin/admins', form);
  return data.data;
};

// 4) تعديل مشرف (أرسل الحقول المتغيّرة فقط)
export const updateSupervisor = async (id, payload) => {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    // القيم المنطقية تُرسل كـ 1/0 داخل FormData
    form.append(key, typeof value === 'boolean' ? (value ? 1 : 0) : value);
  });
  const { data } = await api.post(`/api/admin/admins/${id}`, form);
  return { supervisor: data.data, message: data.message };
};

// 5) حذف مشرف
export const deleteSupervisor = async (id) => {
  const { data } = await api.delete(`/api/admin/admins/${id}`);
  return data.message;
};

// معالجة موحّدة لأخطاء التحقق
export const extractErrors = (error) => {
  const res = error.response;
  if (res?.status === 422) {
    return Object.fromEntries(
      Object.entries(res.data.errors).map(([field, messages]) => [field, messages[0]])
    );
  }
  return { _general: res?.data?.message ?? 'تعذر الاتصال بالخادم.' };
};
```

---

## 12. حالة الاختبار

جميع النقاط الخمس مغطاة باختبارات آلية في
`tests/Feature/AdminSupervisorManagementTest.php` — **32 اختباراً، 195 تأكيداً، كلها ناجحة**.

```bash
php artisan test --filter=AdminSupervisorManagementTest
```
