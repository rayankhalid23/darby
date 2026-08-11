# 🗺️ دليل ربط إدارة الجغرافيا (Municipalities / Sub-Municipalities / Zones)

> وثيقة مخصصة لمطوّر الواجهة الأمامية.
> كل نماذج الـ JSON **ملتقطة من استجابات حقيقية** بعد اختبار كل نقطة على السيرفر.
> مغطاة بـ **30 اختباراً آلياً ناجحاً** في `tests/Feature/AdminGeographyTest.php`.

---

## 1. الهرم الجغرافي — ثلاثة مستويات

```
🏛️  بلدية        Municipality        مثال: طرابلس المركز
 └─ 🏘️  محلة       SubMunicipality     مثال: النوفليين
     └─ 📍 منطقة    Zone                مثال: بن عاشور
```

**قواعد ثابتة:**
- كل محلة **يجب** أن تتبع بلدية واحدة.
- كل منطقة **يجب** أن تتبع محلة واحدة.
- الحذف **يتسلسل للأسفل**: حذف بلدية يحذف محلاتها ومناطقها كلها.
- المعرّف الأب يأتي **من المسار (URL)** وليس من جسم الطلب — لا ترسل `municipality_id` أو `sub_municipality_id` في الـ Body إطلاقاً.

| البند | القيمة |
|---|---|
| **Base URL** | `{APP_URL}/api/admin` |
| **المصادقة** | `Authorization: Bearer {token}` + `Accept: application/json` |
| **الإضافة والتعديل** | `POST` (وليس `PUT`) |

---

## 2. جدول نقاط النهاية الكامل

### 🏛️ البلديات
| العملية | Method | المسار |
|---|---|---|
| عرض كل البلديات | `GET` | `/api/admin/municipalities` |
| عرض بلدية + محلاتها | `GET` | `/api/admin/municipalities/{id}` |
| إضافة بلدية | `POST` | `/api/admin/municipalities` |
| تعديل اسم بلدية | `POST` | `/api/admin/municipalities/{id}` |
| حذف بلدية | `DELETE` | `/api/admin/municipalities/{id}` |

### 🏘️ المحلات
| العملية | Method | المسار |
|---|---|---|
| عرض محلات بلدية | `GET` | `/api/admin/municipalities/{municipalityId}/sub-municipalities` |
| إضافة محلة لبلدية | `POST` | `/api/admin/municipalities/{municipalityId}/sub-municipalities` |
| عرض محلة + مناطقها | `GET` | `/api/admin/sub-municipalities/{id}` |
| تعديل اسم محلة | `POST` | `/api/admin/sub-municipalities/{id}` |
| حذف محلة | `DELETE` | `/api/admin/sub-municipalities/{id}` |

### 📍 المناطق
| العملية | Method | المسار |
|---|---|---|
| عرض مناطق محلة | `GET` | `/api/admin/sub-municipalities/{subMunicipalityId}/zones` |
| **عرض كل مناطق بلدية** (مسطّحة) | `GET` | `/api/admin/municipalities/{municipalityId}/zones` |
| إضافة منطقة لمحلة | `POST` | `/api/admin/sub-municipalities/{subMunicipalityId}/zones` |
| عرض تفاصيل منطقة | `GET` | `/api/admin/admin-zones/{id}` |
| تعديل اسم منطقة | `POST` | `/api/admin/admin-zones/{id}` |
| حذف منطقة | `DELETE` | `/api/admin/admin-zones/{id}` |

> ⚠️ **لماذا `admin-zones` وليس `zones`؟**
> المسار `/api/admin/zones` قديم ويستخدمه تطبيقا السائق وولي الأمر، فتُرك كما هو دون أي تعديل.
> **لا تستخدمه في لوحة التحكم** — استخدم `admin-zones`.

---

## 3. الكائنات

### كائن البلدية

```json
{
  "id": 3,
  "name": "سوق الجمعة",
  "sub_municipalities_count": 1,
  "zones_count": 2,
  "created_at": "2026-08-09 17:41:51",
  "updated_at": "2026-08-09 17:41:51"
}
```

| الحقل | ملاحظات |
|---|---|
| `sub_municipalities_count` | عدد المحلات المباشرة |
| `zones_count` | **إجمالي** المناطق في كل محلات البلدية |
| `sub_municipalities` | مصفوفة المحلات — تظهر فقط في `GET /{id}` أو مع `?with_children=1` |

### كائن المحلة

```json
{
  "id": 4,
  "name": "سوق الجمعة المركز",
  "municipality_id": 3,
  "municipality_name": "سوق الجمعة",
  "zones_count": 2,
  "created_at": "2026-08-09 17:41:51",
  "updated_at": "2026-08-09 17:41:51"
}
```

> `zones` (مصفوفة المناطق) تظهر فقط في `GET /api/admin/sub-municipalities/{id}`.

### كائن المنطقة

```json
{
  "id": 11,
  "name": "شرفة الملاحة",
  "sub_municipality_id": 4,
  "sub_municipality_name": "سوق الجمعة المركز",
  "municipality_id": 3,
  "municipality_name": "سوق الجمعة",
  "full_path": "سوق الجمعة ← سوق الجمعة المركز ← شرفة الملاحة",
  "drivers_count": 0,
  "schools_count": 1,
  "addresses_count": 0,
  "can_delete": false,
  "created_at": "2026-08-09 17:41:51",
  "updated_at": "2026-08-09 17:41:51"
}
```

| الحقل | ملاحظات |
|---|---|
| `full_path` | **المسار الكامل جاهزاً للعرض** — لا تركّبه بنفسك |
| `drivers_count` / `schools_count` / `addresses_count` | ما يرتبط بالمنطقة حالياً |
| `can_delete` | 🔴 **الأهم** — `false` يعني الحذف سيُرفض. **عطّل زر الحذف بناءً عليه** بدل انتظار خطأ 409 |

---

## 4. أمثلة الاستجابات الحقيقية

### عرض كل البلديات

```
GET /api/admin/municipalities?search=&with_children=0
```

```json
{
  "status": true,
  "message": "تم جلب قائمة البلديات بنجاح.",
  "data": [
    { "id": 2, "name": "حي الأندلس",   "sub_municipalities_count": 1, "zones_count": 1,  "created_at": "2026-08-09 17:41:51", "updated_at": "2026-08-09 17:41:51" },
    { "id": 3, "name": "سوق الجمعة",   "sub_municipalities_count": 1, "zones_count": 2,  "created_at": "2026-08-09 17:41:51", "updated_at": "2026-08-09 17:41:51" },
    { "id": 1, "name": "طرابلس المركز", "sub_municipalities_count": 7, "zones_count": 26, "created_at": "2026-08-09 17:41:51", "updated_at": "2026-08-09 17:41:51" }
  ]
}
```

> `data` مصفوفة مباشرة — **لا يوجد ترقيم (pagination)** في هذه الوحدة.
> الترتيب أبجدي حسب الاسم. الفلترة الاختيارية: `?search=سوق`

### عرض بلدية مع محلاتها

```
GET /api/admin/municipalities/3
```

```json
{
  "status": true,
  "message": "تم جلب بيانات البلدية بنجاح.",
  "data": {
    "id": 3,
    "name": "سوق الجمعة",
    "sub_municipalities_count": 1,
    "zones_count": 2,
    "sub_municipalities": [
      {
        "id": 4,
        "name": "سوق الجمعة المركز",
        "municipality_id": 3,
        "zones_count": 2,
        "created_at": "2026-08-09 17:41:51",
        "updated_at": "2026-08-09 17:41:51"
      }
    ],
    "created_at": "2026-08-09 17:41:51",
    "updated_at": "2026-08-09 17:41:51"
  }
}
```

### عرض محلات بلدية

```
GET /api/admin/municipalities/3/sub-municipalities
```

```json
{
  "status": true,
  "message": "تم جلب محلات البلدية بنجاح.",
  "data": {
    "municipality": { "id": 3, "name": "سوق الجمعة" },
    "sub_municipalities": [
      { "id": 4, "name": "سوق الجمعة المركز", "municipality_id": 3, "zones_count": 2, "created_at": "...", "updated_at": "..." }
    ]
  }
}
```

> 🔵 لاحظ أن `data` **كائن** وليس مصفوفة: `data.municipality` و `data.sub_municipalities`.
> هذا يوفّر عليك استدعاءً إضافياً لجلب اسم البلدية لعرضه في العنوان.

### عرض مناطق محلة

```
GET /api/admin/sub-municipalities/4/zones
```

```json
{
  "status": true,
  "message": "تم جلب مناطق المحلة بنجاح.",
  "data": {
    "municipality":     { "id": 3, "name": "سوق الجمعة" },
    "sub_municipality": { "id": 4, "name": "سوق الجمعة المركز" },
    "zones": [
      {
        "id": 11, "name": "شرفة الملاحة",
        "sub_municipality_id": 4, "sub_municipality_name": "سوق الجمعة المركز",
        "municipality_id": 3, "municipality_name": "سوق الجمعة",
        "full_path": "سوق الجمعة ← سوق الجمعة المركز ← شرفة الملاحة",
        "drivers_count": 0, "schools_count": 1, "addresses_count": 0, "can_delete": false,
        "created_at": "...", "updated_at": "..."
      },
      {
        "id": 10, "name": "عرادة",
        "sub_municipality_id": 4, "sub_municipality_name": "سوق الجمعة المركز",
        "municipality_id": 3, "municipality_name": "سوق الجمعة",
        "full_path": "سوق الجمعة ← سوق الجمعة المركز ← عرادة",
        "drivers_count": 0, "schools_count": 0, "addresses_count": 0, "can_delete": true,
        "created_at": "...", "updated_at": "..."
      }
    ]
  }
}
```

### عرض كل مناطق بلدية (مسطّحة عبر كل محلاتها)

```
GET /api/admin/municipalities/1/zones
```

نفس الشكل السابق لكن بلا مفتاح `sub_municipality`:

```json
{
  "status": true,
  "message": "تم جلب مناطق البلدية بنجاح.",
  "data": {
    "municipality": { "id": 1, "name": "طرابلس المركز" },
    "zones": [ "..." ]
  }
}
```

> 💡 مفيدة لقائمة منسدلة تختار منها منطقة داخل بلدية دون إجبار المستخدم على اختيار المحلة أولاً.

---

## 5. الإضافة

الجسم في المستويات الثلاثة **حقل واحد فقط**:

```json
{ "name": "الاسم" }
```

| المستوى | المسار | الاسم |
|---|---|---|
| بلدية | `POST /api/admin/municipalities` | 2–100 حرف، **فريد على مستوى النظام** |
| محلة | `POST /api/admin/municipalities/{id}/sub-municipalities` | 2–100 حرف، فريد **داخل بلديته فقط** |
| منطقة | `POST /api/admin/sub-municipalities/{id}/zones` | 2–100 حرف، فريد **داخل محلته فقط** |

> ℹ️ التفرّد داخل الأب فقط: يمكن أن توجد محلة اسمها "المركز" في كل بلدية، ومنطقة اسمها "حي النصر" في أكثر من محلة.

### ✅ نجاح (201)

```json
{
  "status": true,
  "message": "تم إضافة المنطقة بنجاح.",
  "data": {
    "id": 53,
    "name": "منطقة تجريبية",
    "sub_municipality_id": 35,
    "sub_municipality_name": "محلة تجريبية",
    "municipality_id": 32,
    "municipality_name": "بلدية تجريبية",
    "full_path": "بلدية تجريبية ← محلة تجريبية ← منطقة تجريبية",
    "created_at": "2026-08-11 06:27:46",
    "updated_at": "2026-08-11 06:27:46"
  }
}
```

الرسائل: `تم إضافة البلدية بنجاح.` / `تم إضافة المحلة بنجاح.` / `تم إضافة المنطقة بنجاح.`

### ❌ أخطاء الإضافة

**422 — حقل ناقص أو غير صالح** (شكل Laravel القياسي مع `errors`):
```json
{
  "status": false,
  "message": "عذراً، بيانات المنطقة تحتوي على أخطاء.",
  "errors": { "name": ["يرجى إدخال اسم المنطقة، هذا الحقل إجباري."] }
}
```

**422 — اسم مكرر داخل الأب** (⚠️ **بلا مفتاح `errors`** — رسالة نصية فقط):
```json
{
  "status": false,
  "message": "توجد منطقة بنفس الاسم في محلة النوفليين بالفعل."
}
```

> 🔴 **مهم:** عالج الحالتين. عند 422 افحص وجود `errors` أولاً؛ إن لم يوجد فاعرض `message` تحت الحقل.

**404 — الأب غير موجود:**
```json
{ "status": false, "message": "عذراً، المحلة غير موجودة." }
```

### كل رسائل التحقق

| الحقل | الحالة | الرسالة |
|---|---|---|
| بلدية | فارغ | يرجى إدخال اسم البلدية، هذا الحقل إجباري. |
| بلدية | أقل من حرفين | اسم البلدية قصير جداً، يجب ألا يقل عن حرفين. |
| بلدية | أكثر من 100 | اسم البلدية طويل جداً، يجب ألا يتجاوز 100 حرف. |
| بلدية | مكرر | هذه البلدية مسجلة مسبقاً في النظام. |
| محلة | فارغ | يرجى إدخال اسم المحلة، هذا الحقل إجباري. |
| محلة | مكرر في نفس البلدية | توجد محلة بنفس الاسم في بلدية {اسم} بالفعل. |
| منطقة | فارغ | يرجى إدخال اسم المنطقة، هذا الحقل إجباري. |
| منطقة | مكرر في نفس المحلة | توجد منطقة بنفس الاسم في محلة {اسم} بالفعل. |

---

## 6. التعديل

```
POST /api/admin/municipalities/{id}
POST /api/admin/sub-municipalities/{id}
POST /api/admin/admin-zones/{id}
```

الجسم: `{ "name": "الاسم الجديد" }` — **الاسم فقط**.
لا يمكن نقل منطقة من محلة لأخرى عبر هذه النقطة.

```json
{
  "status": true,
  "message": "تم تحديث اسم المنطقة بنجاح.",
  "data": { "...": "الكائن بعد التحديث" }
}
```

---

## 7. الحذف

```
DELETE /api/admin/municipalities/{id}
DELETE /api/admin/sub-municipalities/{id}
DELETE /api/admin/admin-zones/{id}
```

### ✅ نجاح (200) — بلا مفتاح `data`

```json
{ "status": true, "message": "تم حذف بلدية (سوق الجمعة) وما تتبعها من 1 محلة و2 منطقة بنجاح." }
```
```json
{ "status": true, "message": "تم حذف محلة (النوفليين) وما تتبعها من 2 منطقة بنجاح." }
```
```json
{ "status": true, "message": "تم حذف منطقة (عرادة) بنجاح." }
```

> ⚠️ **الحذف يتسلسل ونهائي.** الرسالة تذكر بالضبط كم عنصراً سيُحذف —
> اعرض نافذة تأكيد تحذيرية، خصوصاً للبلدية.

### ❌ 409 — الحذف مرفوض لوجود ارتباطات

```json
{
  "status": false,
  "message": "لا يمكن حذف هذه المنطقة لأنها مستخدمة حالياً (3 سائق و 1 عنوان). يرجى نقلها أو حذفها أولاً."
}
```
```json
{
  "status": false,
  "message": "لا يمكن حذف هذه البلدية لأن مناطقها مستخدمة حالياً (1 مدرسة). يرجى نقلها أو حذفها أولاً."
}
```

الرسالة تفصّل بالضبط ما الذي يمنع الحذف (سائقون / مدارس / عناوين) — **اعرضها كما هي**.

> 💡 **الأفضل:** استخدم `can_delete` لتعطيل الزر مسبقاً، واجعل 409 شبكة أمان فقط.

### ❌ 404

```json
{ "status": false, "message": "عذراً، البلدية غير موجودة." }
```
(وبالمثل: `المحلة غير موجودة` / `المنطقة غير موجودة`)

---

## 8. أكواد الحالة

| الكود | المعنى | الإجراء |
|---|---|---|
| `200` | نجحت | اعرض `message` وحدّث القائمة |
| `201` | تم الإنشاء | اعرض `message` وأضف العنصر |
| `401` | غير مصادق | سجّل الخروج ووجّه للدخول |
| `404` | غير موجود | اعرض `message` وحدّث القائمة |
| `409` | الحذف مرفوض لارتباطات | اعرض `message` كتحذير |
| `422` | بيانات غير صالحة | `errors` إن وُجد، وإلا `message` |
| `500` | خطأ سيرفر | رسالة عامة |

---

## 9. كود جاهز (Dio)

```dart
// ───── البلديات ─────
Future<List<Municipality>> getMunicipalities({String? search}) async {
  final res = await api.get('/api/admin/municipalities',
      queryParameters: {if (search != null && search.isNotEmpty) 'search': search});
  return (res.data['data'] as List).map(Municipality.fromJson).toList();
}

Future<Municipality> getMunicipality(int id) async {
  final res = await api.get('/api/admin/municipalities/$id');
  return Municipality.fromJson(res.data['data']); // يتضمن sub_municipalities
}

Future<Municipality> createMunicipality(String name) async {
  final res = await api.post('/api/admin/municipalities', data: {'name': name});
  return Municipality.fromJson(res.data['data']);
}

Future<String> deleteMunicipality(int id) async {
  final res = await api.delete('/api/admin/municipalities/$id');
  return res.data['message'];
}

// ───── المحلات ─────
Future<SubMunicipalitiesPage> getSubMunicipalities(int municipalityId) async {
  final res = await api.get('/api/admin/municipalities/$municipalityId/sub-municipalities');
  return SubMunicipalitiesPage(
    municipality: res.data['data']['municipality'],
    items: (res.data['data']['sub_municipalities'] as List)
        .map(SubMunicipality.fromJson).toList(),
  );
}

Future<SubMunicipality> createSubMunicipality(int municipalityId, String name) async {
  final res = await api.post(
      '/api/admin/municipalities/$municipalityId/sub-municipalities', data: {'name': name});
  return SubMunicipality.fromJson(res.data['data']);
}

Future<String> deleteSubMunicipality(int id) async {
  final res = await api.delete('/api/admin/sub-municipalities/$id');
  return res.data['message'];
}

// ───── المناطق ─────
Future<List<Zone>> getZonesOfSubMunicipality(int subId) async {
  final res = await api.get('/api/admin/sub-municipalities/$subId/zones');
  return (res.data['data']['zones'] as List).map(Zone.fromJson).toList();
}

Future<List<Zone>> getZonesOfMunicipality(int municipalityId) async {
  final res = await api.get('/api/admin/municipalities/$municipalityId/zones');
  return (res.data['data']['zones'] as List).map(Zone.fromJson).toList();
}

Future<Zone> createZone(int subMunicipalityId, String name) async {
  final res = await api.post(
      '/api/admin/sub-municipalities/$subMunicipalityId/zones', data: {'name': name});
  return Zone.fromJson(res.data['data']);
}

Future<Zone> getZone(int id) async {
  final res = await api.get('/api/admin/admin-zones/$id');
  return Zone.fromJson(res.data['data']);
}

Future<String> deleteZone(int id) async {
  final res = await api.delete('/api/admin/admin-zones/$id');
  return res.data['message'];
}

// ───── معالجة الأخطاء الموحّدة ─────
String? fieldError(DioException e, String field) {
  final res = e.response;
  if (res?.statusCode == 422) {
    final errors = res!.data['errors'];
    if (errors != null && errors[field] != null) return errors[field][0];
    return res.data['message'];   // تكرار الاسم يأتي بلا errors
  }
  if (res?.statusCode == 409) return res!.data['message']; // الحذف مرفوض
  return res?.data?['message'] ?? 'تعذر الاتصال بالخادم.';
}
```

---

## 10. ✅ ملخص ما يجب بناؤه

1. **شاشة البلديات** — قائمة بأسماء البلديات وعدد المحلات والمناطق، مع بحث وزر إضافة.
2. **شاشة البلدية** — تعرض محلاتها، مع زر إضافة محلة.
3. **شاشة المحلة** — تعرض مناطقها، مع زر إضافة منطقة.
4. **شاشة تفاصيل منطقة** — `full_path` + عدّادات الاستخدام.
5. **زر الحذف معطّل** عندما `can_delete == false`، مع تلميح يوضح السبب.
6. **نافذة تأكيد قبل أي حذف** — تُبرز أن حذف البلدية يحذف كل ما تحتها.
7. عند 422 **افحص وجود `errors`** قبل قراءته — تكرار الاسم يأتي برسالة مباشرة بلا `errors`.

## ⚠️ ملاحظتان

- **لا تستخدم `/api/admin/zones` القديم** في لوحة التحكم — هو لتطبيقي السائق وولي الأمر. استخدم `admin-zones`.
- توجد حالياً **بلديتان بنفس الاسم** ("طرابلس المركز" بمعرّفين 1 و 4) من بيانات سابقة. قاعدة التفرّد تمنع التكرار الجديد لكنها لا تصلح القديم — اعرض `id` عند الالتباس.
